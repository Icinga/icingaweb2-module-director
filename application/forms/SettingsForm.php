<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Module\Director\Settings;
use Icinga\Module\Director\Web\Form\DirectorForm;

class SettingsForm extends DirectorForm
{
    /** @var Settings */
    protected $settings;

    /**
     * @throws \Zend_Form_Exception
     */
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
        $globalZones = $this->eventuallyConfiguredEnum('default_global_zone', $this->enumGlobalZones());

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

        $this->addElement('text', 'icinga_package_name', array(
            'label'        => $this->translate('Icinga Package Name'),
            'description'  => $this->translate(
                'The Icinga Package name Director uses to deploy it\'s configuration.'
                . ' This defaults to "director" and should not be changed unless'
                . ' you really know what you\'re doing'
            ),
            'placeholder'  => $settings->get('icinga_package_name'),
            'value'  => $settings->getStoredValue('icinga_package_name')
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
            'value' => $settings->getStoredValue('disable_all_jobs')
        ));

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
                . ' written to either Syslog or the configured log file. When'
                . ' enabling this please make sure that you configured Icinga'
                . ' Web 2 to log at least at "informational" level.'
            ),
            'value' => $settings->getStoredValue('enable_audit_log')
        ));

        if ($settings->getStoredValue('ignore_bug7530')) {
            // Show this only for those who touched this setting
            $this->addElement('select', 'ignore_bug7530', array(
                'label'        => $this->translate('Ignore Bug #7530'),
                'multiOptions' => $this->eventuallyConfiguredEnum(
                    'ignore_bug7530',
                    array(
                        'n' => $this->translate('No'),
                        'y' => $this->translate('Yes'),
                    )
                ),
                'description'  => $this->translate(
                    'Icinga v2.11.0 breaks some configurations, the Director will'
                    . ' warn you before every deployment in case your config is'
                    . ' affected. This setting allows to hide this warning.'
                ),
                'value' => $settings->getStoredValue('ignore_bug7530')
            ));
        }

        $this->addBoolean('feature_custom_endpoint', [
            'label'       => $this->translate('Feature: Custom Endpoint Name'),
            'description' => $this->translate(
                'Enabled the feature for custom endpoint names,'
                . ' where you can choose a different name for the generated endpoint object.'
                . ' This uses some Icinga config snippets and a special custom variable.'
                . ' Please do NOT enable this, unless you really need divergent endpoint names!'
            ),
            'value'      => $settings->getStoredValue('feature_custom_endpoint')
        ]);


        $this->addElement('select', 'config_format', array(
            'label'        => $this->translate('Configuration format'),
            'multiOptions' => $this->eventuallyConfiguredEnum(
                'config_format',
                array(
                    'v2' => $this->translate('Icinga v2.x'),
                    'v1' => $this->translate('Icinga v1.x'),
                )
            ),
            'description'  => $this->translate(
                'Default configuration format. Please note that v1.x is for'
                . ' special transitional projects only and completely'
                . ' unsupported. There are no plans to make Director a first-'
                . 'class configuration backends for Icinga 1.x'
            ),
            'class' => 'autosubmit',
            'value' => $settings->getStoredValue('config_format')
        ));

        $this->setSubmitLabel($this->translate('Store'));

        if ($this->hasBeenSent()) {
            if ($this->getSentValue('config_format') !== 'v1') {
                return;
            }
        } elseif ($settings->getStoredValue('config_format') !== 'v1') {
            return;
        }

        $this->addElement('select', 'deployment_mode_v1', array(
            'label'        => $this->translate('Deployment mode'),
            'multiOptions' => $this->eventuallyConfiguredEnum(
                'deployment_mode_v1',
                array(
                    'active-passive' => $this->translate('Active-Passive'),
                    'masterless'     => $this->translate('Master-less'),
                )
            ),
            'description'  => $this->translate(
                'Deployment mode for Icinga 1 configuration'
            ),
            'value' => $settings->getStoredValue('deployment_mode_v1')
        ));

        $this->addElement('text', 'deployment_path_v1', array(
            'label'        => $this->translate('Deployment Path'),
            'description'  => $this->translate(
                'Local directory to deploy Icinga 1.x configuration.'
                . ' Must be writable by icingaweb2.'
                . ' (e.g. /etc/icinga/director)'
            ),
            'value' => $settings->getStoredValue('deployment_path_v1')
        ));

        $this->addElement('text', 'activation_script_v1', array(
            'label'        => $this->translate('Activation Tool'),
            'description'  => $this->translate(
                'Script or tool to call when activating a new configuration stage.'
                . ' (e.g. sudo /usr/local/bin/icinga-director-activate)'
                . ' (name of the stage will be the argument for the script)'
            ),
            'value' => $settings->getStoredValue('activation_script_v1')
        ));
    }

    protected function eventuallyConfiguredEnum($name, $enum)
    {
        if (array_key_exists($name, $enum)) {
            $default = sprintf(
                $this->translate('%s (default)'),
                $enum[$this->settings->getDefaultValue($name)]
            );
        } else {
            $default = $this->translate('- please choose -');
        }

        return ['' => $default] + $enum;
    }

    public function setSettings(Settings $settings)
    {
        $this->settings = $settings;
        return $this;
    }

    protected function enumGlobalZones()
    {
        $db = $this->settings->getDb();
        $zones = $db->fetchCol(
            $db->select()->from('icinga_zone', 'object_name')
                ->where('disabled = ?', 'n')
                ->where('is_global = ?', 'y')
                ->order('object_name')
        );

        return array_combine($zones, $zones);
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
