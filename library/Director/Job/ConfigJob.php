<?php

namespace Icinga\Module\Director\Job;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Web\Form\QuickForm;

class ConfigJob extends JobHook
{
    protected $lastDeployment;

    protected $api;

    public function run()
    {
        $db = $this->db();

        if (DirectorDeploymentLog::hasUncollected($db)) {
            $this->api()->collectLogFiles($db);
        }

        $this->clearLastDeployment();

        if ($this->shouldGenerate()) {
            $config = IcingaConfig::generate($db);
        } else {
            $config = $this->loadLatestActivityConfig();
        }

        if ($this->shouldDeploy($config)) {
            $this->deploy($config);
        }

        $this->clearLastDeployment();
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
        return $this->getSetting('force_generate') === 'y'
            || ! $this->configForLatestActivityExists();
    }

    protected function configForLatestActivityExists()
    {
        $db = $this->db();

        return IcingaConfig::existsForActivityChecksum(
            bin2hex(DirectorActivityLog::loadLatest($db)->checksum),
            $db
        );
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

        if (DirectorDeploymentLog::loadLatest($db)->getConfigHexChecksum()
            === $config->getHexChecksum()
        ) {
            return false;
        }

        if ($this->getActiveChecksum() === $config->getHexChecksum()) {
            return false;
        }

        return true;
    }

    protected function deploy(IcingaConfig $config)
    {
        $db = $this->db();
        $api = $this->api();
        $api->wipeInactiveStages($db);

        $checksum = $config->getHexChecksum();
        $this->info('Director ConfigJob ready to deploy "%s"', $checksum);
        if ($api->dumpConfig($config, $db)) {
            $this->info('Director ConfigJob deployed config "%s"', $checksum);

            // TODO: Loop and try multiple times?
            sleep(2);
            try {
                $api->collectLogFiles($db);
            } catch (Exception $e) {
                // Ignore those errors, Icinga may be reloading
            }
        } else {
            throw new IcingaException('Failed to deploy config "%s"', $checksum);
        }
    }

    protected function getGracePeriodStart()
    {
        return time() - $this->getSetting('grace_period');
    }

    public function getRemainingGraceTime()
    {
        if ($this->isWithinGracePeriod()) {
            if ($deployment = $this->lastDeployment()) {
                return $deployment->getDeploymentTimestamp()
                + $this->getSetting('grace_period')
                - time();
            } else {
                return null;
            }
        }

        return 0;
    }

    protected function isWithinGracePeriod()
    {
        if ($deployment = $this->lastDeployment()) {
            return $deployment->getDeploymentTimestamp() > $this->getGracePeriodStart();
        }

        return false;
    }

    protected function getActiveChecksum()
    {
        return DirectorDeploymentLog::getConfigChecksumForStageName(
            $this->db(),
            $this->api()->getActiveStageName()
        );
    }

    protected function lastDeployment()
    {
        if ($this->lastDeployment === null) {
            $this->lastDeployment = DirectorDeploymentLog::loadLatest($this->db());
        }

        return $this->lastDeployment;
    }

    protected function clearLastDeployment()
    {
        $this->lastDeployment = null;
        return $this;
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
            . ' Icinga 2 configuration'
        );
    }
}
