<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Module\Director\Settings;
use Icinga\Module\Director\Web\Form\QuickForm;

class SettingsForm extends QuickForm
{
    /** @var Settings */
    protected $settings;

    public function setup()
    {
        $settings = $this->settings;

        $this->addHtmlHint(
            $this->translate(
                'Please only change those settings in case you are really sure'
                . ' that you are required to do so. Usually the defaults chosen'
                . ' by the Icinga Director should make a good fit for your'
                . ' environment.'
            )
        );

        $globalZones = array(
            null => sprintf(
                $this->translate('%s (default)'),
                $settings->getDefaultValue('default_global_zone')
            )
        );

        $this->addElement('select', 'default_global_zone', array(
            'label'        => $this->translate('Default global zone'),
            'multiOptions' => $globalZones,
            'description'  => $this->translate(
                'Icinga Director decides to deploy objects like CheckCommands'
                . ' to a global zone. This defaults to "director-global" but'
                . ' might be adjusted to a custom Zone name'
            ),
            'value'  => $settings->getStoredValue('default_global_zone')
        ));

        $this->addElement('select', 'disable_all_jobs', array(
            'label'        => $this->translate('Disable all Jobs'),
            'multiOptions' => $this->eventuallyConfiguredEnum(
                'disable_all_jobs',
                array(
                    'n' => $this->translate('No'),
                    'y' => $this->translate('Yes'),
                )
            ),
            'description'  => $this->translate(
                'Whether all configured Jobs should be disabled'
            ),
        ));

        $this->getElement('disable_all_jobs')->setValue(
            $settings->getStoredValue('disable_all_jobs')
        );

        $this->addElement('select', 'enable_audit_log', array(
            'label'        => $this->translate('Enable audit log'),
            'multiOptions' => $this->eventuallyConfiguredEnum(
                'enable_audit_log',
                array(
                    'n' => $this->translate('No'),
                    'y' => $this->translate('Yes'),
                )
            ),
            'description'  => $this->translate(
                'All changes are tracked in the Director database. In addition'
                . ' you might also want to send an audit log through the Icinga'
                . " Web 2 logging mechanism. That way all changes would be"
                . ' written to either Syslog or the configured log file'
            ),
        ));

        $this->getElement('disable_all_jobs')->setValue(
            $settings->getStoredValue('disable_all_jobs')
        );

        $this->addElement('select', 'config_format', array(
            'label'        => $this->translate('Configuration format'),
            'multiOptions' => $this->eventuallyConfiguredEnum(
                'config_format',
                array(
                    'v2' => $this->translate('Icinga v2.x'),
                    'v1' => $this->translate('Icinga v1.x'),
                    // Hiding for now 'v1-masterless' => $this->translate('Icinga v1.x (no master)'),
                )
            ),
            'description'  => $this->translate(
                'Default configuration format. Please note that v1.x is for'
                . ' special transitional projects only and completely'
                . ' unsupported. There are no plans to make Director a first-'
                . 'class configuration backends for Icinga 1.x'
            ),
        ));

        $this->getElement('config_format')->setValue(
            $settings->getStoredValue('config_format')
        );

        $this->setSubmitLabel($this->translate('Store'));
    }

    protected function eventuallyConfiguredEnum($name, $enum)
    {
        return array(
            null => sprintf(
                $this->translate('%s (default)'),
                $enum[$this->settings->getDefaultValue($name)]
            )
        ) + $enum;
    }

    public function setSettings(Settings $settings)
    {
        $this->settings = $settings;
        return $this;
    }

    public function onSuccess()
    {
        try {
            foreach ($this->getValues() as $key => $value) {
                if ($value === '') {
                    $value = null;
                }

                $this->settings->set($key, $value);
            }

            $this->setSuccessMessage($this->translate(
                'Settings have been stored'
            ));

            parent::onSuccess();
        } catch (Exception $e) {
            $this->addError($e->getMessage());
        }
    }
}
