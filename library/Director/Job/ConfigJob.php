<?php

namespace Icinga\Module\Director\Job;

use Icinga\Application\Benchmark;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Util;

class ConfigJob extends JobHook
{
    public function run()
    {
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
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

        return $form;
    }


    public function isPending()
    {
        return false;
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
    public function renderAction()
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
