<?php

namespace Icinga\Module\Director\Core;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;

/**
 * Legacy DeploymentApi for Icinga 1.x configuration deployment
 *
 * @package Icinga\Module\Director\Core
 */
class LegacyDeploymentApi implements DeploymentApiInterface
{
    protected $db;
    protected $deploymentPath;
    protected $activationScript;

    protected $dir_mode;
    protected $file_mode;

    public function __construct(Db $db)
    {
        $this->db = $db;
        $settings = $this->db->settings();
        $this->deploymentPath = $settings->deployment_path_v1;
        $this->activationScript = $settings->activation_script_v1;

        $this->dir_mode = base_convert($settings->get('deployment_file_mode_v1', '2775'), 8, 10);
        $this->file_mode = base_convert($settings->get('deployment_dir_mode_v1', '0664'), 8, 10);
    }

    /**
     * TODO: merge in common class
     * @inheritdoc
     */
    public function collectLogFiles(Db $db)
    {
        $packageName = $db->settings()->get('icinga_package_name');
        $existing = $this->listPackageStages($packageName);

        foreach (DirectorDeploymentLog::getUncollected($db) as $deployment) {
            $stage = $deployment->get('stage_name');
            if (! in_array($stage, $existing)) {
                continue;
            }

            try {
                $availableFiles = $this->listStageFiles($stage);
            } catch (Exception $e) {
                // Could not collect stage files. Doesn't matter, let's try next time
                continue;
            }

            if (in_array('startup.log', $availableFiles)
                && in_array('status', $availableFiles)
            ) {
                $status = $this->getStagedFile($stage, 'status');
                $status = trim($status);
                if ($status === '0') {
                    $deployment->set('startup_succeeded', 'y');
                } else {
                    $deployment->set('startup_succeeded', 'n');
                }
                $deployment->set('startup_log', $this->shortenStartupLog(
                    $this->getStagedFile($stage, 'startup.log')
                ));
            } else {
                // Stage seems to be incomplete, let's try again next time
                continue;
            }
            $deployment->set('stage_collected', 'y');

            $deployment->store();
        }
    }

    /**
     * TODO: merge in common class
     * @inheritdoc
     */
    public function wipeInactiveStages(Db $db)
    {
        $uncollected = DirectorDeploymentLog::getUncollected($db);
        $packageName = $db->settings()->get('icinga_package_name');
        $currentStage = $this->getActiveStageName();

        // try to expire old deployments
        foreach ($uncollected as $name => $deployment) {
            /** @var DirectorDeploymentLog $deployment */
            if ($deployment->get('dump_succeeded') === 'n'
                || $deployment->get('startup_succeeded') === null
            ) {
                $start_time = strtotime($deployment->start_time);

                // older than an hour and no startup
                if ($start_time + 3600 < time()) {
                    $deployment->set('startup_succeeded', 'n');
                    $deployment->set('startup_log', 'Activation timed out...');
                    $deployment->store();
                }
            }
        }

        foreach ($this->listPackageStages($packageName) as $stage) {
            if (array_key_exists($stage, $uncollected)
                && $uncollected[$stage]->get('startup_succeeded') === null
            ) {
                continue;
            } elseif ($stage === $currentStage) {
                continue;
            } else {
                $this->deleteStage($packageName, $stage);
            }
        }
    }

    /** @inheritdoc */
    public function getActiveStageName()
    {
        $this->assertDeploymentPath();

        $path = $this->deploymentPath . DIRECTORY_SEPARATOR . 'active';

        if (file_exists($path)) {
            if (is_link($path)) {
                $linkTarget = readlink($path);
                if (! $linkTarget) {
                    throw new IcingaException('Failed to read symlink');
                }

                $linkTargetDir = dirname($linkTarget);
                $linkTargetName = basename($linkTarget);

                if ($linkTargetDir === $this->deploymentPath || $linkTargetDir === '.') {
                    return $linkTargetName;
                } else {
                    throw new IcingaException(
                        'Active stage link pointing to a invalid target: %s -> %s',
                        $path,
                        $linkTarget
                    );
                }
            } else {
                throw new IcingaException('Active stage is not a symlink: %s', $path);
            }
        } else {
            return false;
        }
    }

    /** @inheritdoc */
    public function listStageFiles($stage)
    {
        $path = $this->getStagePath($stage);
        if (! is_dir($path)) {
            throw new IcingaException('Deployment stage "%s" does not exist at: %s', $stage, $path);
        }
        return $this->listDirectoryContents($path);
    }

    /** @inheritdoc */
    public function listPackageStages($packageName)
    {
        $this->assertPackageName($packageName);
        $this->assertDeploymentPath();

        $dh = @opendir($this->deploymentPath);
        if ($dh === false) {
            throw new IcingaException('Can not list contents of %s', $this->deploymentPath);
        }

        $stages = array();
        while ($file = readdir($dh)) {
            if ($file === '.' || $file === '..') {
                continue;
            } elseif (is_dir($this->deploymentPath . DIRECTORY_SEPARATOR . $file)
                && substr($file, 0, 9) === 'director-'
            ) {
                $stages[] = $file;
            }
        }

        return $stages;
    }

    /** @inheritdoc */
    public function getStagedFile($stage, $file)
    {
        $path = $this->getStagePath($stage);

        $filePath = $path . DIRECTORY_SEPARATOR . $file;

        if (! file_exists($filePath)) {
            throw new IcingaException('Could not find file %s', $filePath);
        } else {
            return file_get_contents($filePath);
        }
    }

    /** @inheritdoc */
    public function deleteStage($packageName, $stageName)
    {
        $this->assertPackageName($packageName);
        $this->assertDeploymentPath();

        $path = $this->getStagePath($stageName);

        static::rrmdir($path);
    }

    /** @inheritdoc */
    public function dumpConfig(IcingaConfig $config, Db $db, $packageName = null)
    {
        if ($packageName === null) {
            $packageName = $db->settings()->get('icinga_package_name');
        }
        $this->assertPackageName($packageName);
        $this->assertDeploymentPath();

        $start = microtime(true);
        $deployment = DirectorDeploymentLog::create(array(
            // 'config_id'      => $config->id,
            // 'peer_identity'  => $endpoint->object_name,
            'peer_identity'   => $this->deploymentPath,
            'start_time'      => date('Y-m-d H:i:s'),
            'config_checksum' => $config->getChecksum(),
            'last_activity_checksum' => $config->getLastActivityChecksum()
            // 'triggered_by'   => Util::getUsername(),
            // 'username'       => Util::getUsername(),
            // 'module_name'    => $moduleName,
        ));

        $stage_name = 'director-' .date('Ymd-His');
        $deployment->set('stage_name', $stage_name);

        try {
            $succeeded = $this->deployStage($stage_name, $config->getFileContents());
            if ($succeeded === true) {
                $succeeded = $this->activateStage($stage_name);
            }
        } catch (Exception $e) {
            $deployment->set('dump_succeeded', 'n');
            $deployment->set('startup_log', $e->getMessage());
            $deployment->set('startup_succeeded', 'n');
            $deployment->store($db);
            throw $e;
        }

        $duration = (int) ((microtime(true) - $start) * 1000);
        $deployment->set('duration_dump', $duration);

        $deployment->set('dump_succeeded', $succeeded === true ? 'y' : 'n');

        $deployment->store($db);
        return $succeeded;
    }

    /**
     * Deploy a new stage, and write all files to it
     *
     * @param  string  $stage  Name of the stage
     * @param  array   $files  Array of files, $fileName => $content
     *
     * @return bool    Success status
     *
     * @throws IcingaException  When something could not be accessed
     */
    protected function deployStage($stage, $files)
    {
        $path = $this->deploymentPath . DIRECTORY_SEPARATOR . $stage;

        if (file_exists($path)) {
            throw new IcingaException('Stage "%s" does already exist at: ', $stage, $path);
        } else {
            $this->mkdir($path);

            foreach ($files as $file => $content) {
                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                $this->mkdir(dirname($fullPath), true);

                $fh = @fopen($fullPath, 'w');
                if ($fh === false) {
                    throw new IcingaException('Could not open file "%s" for writing.', $fullPath);
                }
                chmod($fullPath, $this->file_mode);

                fwrite($fh, $content);
                fclose($fh);
            }

            return true;
        }
    }

    /**
     * Starts activation of
     *
     * Note: script should probably fork to background?
     *
     * @param  string  $stage  Stage to activate
     *
     * @return bool
     *
     * @throws IcingaException  For an execution error
     */
    protected function activateStage($stage)
    {
        if ($this->activationScript === null || trim($this->activationScript) === '') {
            // skip activation, could be done by external cron worker
            return true;
        } else {
            $command = sprintf('%s %s 2>&1', escapeshellcmd($this->activationScript), escapeshellarg($stage));
            $output = null;
            $rc = null;
            exec($command, $output, $rc);
            $output = join("\n", $output);
            if ($rc !== 0) {
                throw new IcingaException("Activation script did exit with return code %d:\n\n%s", $rc, $output);
            }
            return true;
        }
    }

    /**
     * Recursively dump directory contents, with relative path
     *
     * @param  string  $path   Absolute path to read from
     * @param  int     $depth  Internal counter
     *
     * @return string[]
     *
     * @throws IcingaException  When directory could not be opened
     */
    protected function listDirectoryContents($path, $depth = 0)
    {
        $dh = @opendir($path);
        if ($dh === false) {
            throw new IcingaException('Can not list contents of %s', $path);
        }

        $files = array();
        while ($file = readdir($dh)) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if ($file === '.' || $file === '..') {
                continue;
            } elseif (is_dir($fullPath)) {
                $subdirFiles = $this->listDirectoryContents($fullPath, $depth + 1);
                foreach ($subdirFiles as $subFile) {
                    $files[] = $file . DIRECTORY_SEPARATOR . $subFile;
                }
            } else {
                $files[] = $file;
            }
        }

        if ($depth === 0) {
            sort($files);
        }

        return $files;
    }

    /**
     * Assert that only the director module is interacted with
     *
     * @param  string  $packageName
     * @throws IcingaException  When another module is requested
     */
    protected function assertPackageName($packageName)
    {
        if ($packageName !== 'director') {
            throw new IcingaException('Does not supported different modules!');
        }
    }

    /**
     * Assert the deployment path to be configured, existing, and writeable
     *
     * @throws IcingaException
     */
    protected function assertDeploymentPath()
    {
        if ($this->deploymentPath === null) {
            throw new IcingaException('Deployment path is not configured for legacy config!');
        } elseif (! is_dir($this->deploymentPath)) {
            throw new IcingaException('Deployment path is not a directory: %s', $this->deploymentPath);
        } elseif (! is_writeable($this->deploymentPath)) {
            throw new IcingaException('Deployment path is not a writeable: %s', $this->deploymentPath);
        }
    }

    /**
     * TODO: avoid code duplication: copied from CoreApi
     *
     * @param  string  $log  The log contents to shorten
     * @return string
     */
    protected function shortenStartupLog($log)
    {
        $logLen = strlen($log);
        if ($logLen < 1024 * 60) {
            return $log;
        }

        $part = substr($log, 0, 1024 * 20);
        $parts = explode("\n", $part);
        array_pop($parts);
        $begin = implode("\n", $parts) . "\n\n";

        $part = substr($log, -1024 * 20);
        $parts = explode("\n", $part);
        array_shift($parts);
        $end = "\n\n" . implode("\n", $parts);

        return $begin . sprintf(
            '[..] %d bytes removed by Director [..]',
            $logLen - (strlen($begin) + strlen($end))
        ) . $end;
    }

    /**
     * Return the full path of a stage
     *
     * @param  string  $stage  Name of the stage
     *
     * @return string
     */
    public function getStagePath($stage)
    {
        $this->assertDeploymentPath();
        return $this->deploymentPath . DIRECTORY_SEPARATOR . $stage;
    }

    /**
     * @from https://php.net/manual/de/function.rmdir.php#108113
     * @param $dir
     */
    protected static function rrmdir($dir)
    {
        foreach (glob($dir . '/*') as $file) {
            if (is_dir($file)) {
                static::rrmdir($file);
            } else {
                unlink($file);
            }
        }

        rmdir($dir);
    }

    protected function mkdir($path, $recursive = false)
    {
        if (! file_exists($path)) {
            if ($recursive) {
                $this->mkdir(dirname($path));
            }

            try {
                mkdir($path);
                chmod($path, $this->dir_mode);
            } catch (Exception $e) {
                throw new IcingaException('Could not create path "%s": %s', $path, $e->getMessage());
            }
        }
    }
}
