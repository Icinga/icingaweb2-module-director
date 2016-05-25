<?php

namespace Icinga\Module\Director\Job;

use Icinga\Application\Benchmark;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Form\QuickForm;

class ConfigJob extends JobHook
{
    protected $lastDeployment;

    protected $api;

    public function run()
    {
        $db = $this->db();

        if ($this->shouldGenerate()) {
            $config = IcingaConfig::generate($db);
        } else {
            $config = $this->loadLatestActivityConfig();
        }

        if ($this->shouldDeploy($config)) {
            $this->deploy($config);
        }
    }

    protected function api()
    {
        if ($this->api === null) {
            $this->api = $this->db()->getDeploymentEndpoint()->api();
        }

        return $this->api;
    }

    protected function loadLatestActivityConfig()
    {
        $db = $this->db();

        return IcingaConfig::loadByActivityChecksum(
            $db->getLastActivityChecksum(),
            $db
        );
    }

    protected function shouldGenerate()
    {
        return $this->getSetting('force_generate')
                // -> last config?!
            || $this->db()->countActivitiesSinceLastDeployedConfig() > 0;
    }

    protected function shouldDeploy(IcingaConfig $config)
    {
        $db = $this->db();

        if ($this->getSetting('deploy_when_changed') !== 'y') {
            return false;
        }
        $api = $this->api();
        $api->collectLogFiles($db);

        if (! DirectorDeploymentLog::hasDeployments($db)) {
            return true;
        }

        if ($this->isWithinGracePeriod()) {
            return false;
        }

        if ($this->lastDeployment()->configEquals($config)) {
            return false;
        }

        // $current = $api->getActiveChecksum($db);
        // TODO: no current, but last deployment
        return true;
    }

    protected function deploy(IcingaConfig $config)
    {
        $db = $this->db();
        $api = $this->api();
        $api->wipeInactiveStages($db);

        $checksum = $config->getHexChecksum();
        if ($api->dumpConfig($config, $db)) {
            $this->printf("Config '%s' has been deployed\n", $checksum);
            $api->collectLogFiles($db);
        } else {
            $this->fail(sprintf("Failed to deploy config '%s'\n", $checksum));
        }
    }

    protected function getGracePeriodStart()
    {
        return time() - $this->getSetting('grace_period');
    }

    protected function isWithinGracePeriod()
    {
        if ($deployment = $this->lastDeployment()) {
            return $deployment->getDeploymentTimestamp() > $this->getGracePeriodStart();
        }

        return false;
    }

    protected function lastDeployment()
    {
        if ($this->lastDeployment === null) {
            $this->lastDeployment = DirectorDeploymentLog::loadLatest($this->db());
        }

        return $this->lastDeployment;
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'force_generate', array(
            'label'        => $form->translate('Force rendering'),
            'description'  => $form->translate(
                'Whether rendering should be forced. If not enforced, this'
                . ' job re-renders the configuration only when there have been'
                . ' activities since the last rendered config'
            ),
            'value'        => 'n',
            'multiOptions' => array(
                'y'  => $form->translate('Yes'),
                'n'  => $form->translate('No'),
            )
        ));

        $form->addElement('select', 'deploy_when_changed', array(
            'label'        => $form->translate('Deploy modified config'),
            'description'  => $form->translate(
                'This allows you to immediately deploy a modified configuration'
            ),
            'value'        => 'n',
            'multiOptions' => array(
                'y'  => $form->translate('Yes'),
                'n'  => $form->translate('No'),
            )
        ));

        $form->addElement('text', 'grace_period', array(
            'label' => $form->translate('Grace period'),
            'description' => $form->translate(
                'When deploying configuration, wait at least this amount of'
                . ' seconds unless the next deployment should take place'
            ),
            'value' => 600,
        ));

        return $form;
    }

    public static function getDescription(QuickForm $form)
    {
        return $form->translate(
            'The Config job allows you to generate and eventually deploy your'
            . 'Icinga 2 configuration'
        );
    }

    /**
     * Re-render the current configuration
     */
    public function renderConfig()
    {
        $config = new IcingaConfig($this->db());
        Benchmark::measure('Rendering config');
        if ($config->hasBeenModified()) {
            Benchmark::measure('Config rendered, storing to db');
            $config->store();
            Benchmark::measure('All done');
            $checksum = $config->getHexChecksum();
            $this->printf(
                "New config with checksum %s has been generated\n",
                $checksum
            );
        } else {
            $checksum = $config->getHexChecksum();
            $this->printf(
                "Config with checksum %s already exists\n",
                $checksum
            );
        }
    }

    /**
     * Deploy the current configuration
     *
     * Does nothing if config didn't change unless you provide
     * the --force parameter
     */
    public function deployAction()
    {
        $api = $this->api();
        $db = $this->db();

        $checksum = $this->params->get('checksum');
        if ($checksum) {
            $config = IcingaConfig::load(Util::hex2binary($checksum), $db);
        } else {
            $config = IcingaConfig::generate($db);
            $checksum = $config->getHexChecksum();
        }

        $api->wipeInactiveStages($db);
        $current = $api->getActiveChecksum($db);
        if ($current === $checksum) {
            if ($this->params->get('force')) {
                echo "Config matches active stage, deploying anyway\n";
            } else {
                echo "Config matches active stage, nothing to do\n";

                return;
            }

        } else {
            if ($api->dumpConfig($config, $db)) {
                $this->printf("Config '%s' has been deployed\n", $checksum);
            } else {
                $this->fail(
                    sprintf("Failed to deploy config '%s'\n", $checksum)
                );
            }
        }
    }
}
