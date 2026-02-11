<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Settings;
use Icinga\Module\Director\Web\Form\DirectorForm;

class SelfServiceSettingsForm extends DirectorForm
{
    /** @var Settings */
    protected $settings;

    public function setup()
    {
        $settings = $this->settings;
        $this->addElement('select', 'agent_name', [
            'label'       => $this->translate('Host Name'),
            'description' => $this->translate(
                'What to use as your Icinga 2 Agent\'s Host Name'
            ),
            'multiOptions' => [
                'fqdn'     => $this->translate('Fully qualified domain name (FQDN)'),
                'hostname' => $this->translate('Host name (local part, without domain)'),
            ],
            'value'  => $settings->getStoredOrDefaultValue('self-service/agent_name')
        ]);

        $this->addElement('select', 'transform_hostname', [
            'label'       => $this->translate('Transform Host Name'),
            'description' => $this->translate(
                'Whether to adjust your host name'
            ),
            'multiOptions' => [
                '0'   => $this->translate('Do not transform at all'),
                '1'   => $this->translate('Transform to lowercase'),
                '2'   => $this->translate('Transform to uppercase'),
            ],
            'value'  => $settings->getStoredOrDefaultValue('self-service/transform_hostname')
        ]);
        $this->addElement('select', 'resolve_parent_host', [
            'label'       => $this->translate('Transform Parent Host to IP'),
            'description' => $this->translate(
                'This is only important in case your master/satellite nodes do not'
                . ' have IP addresses as their "host" property. The Agent can be'
                . ' told to issue related DNS lookups on it\' own'
            ),
            'multiOptions' => [
                '0'   => $this->translate("Don't care, my host settings are fine"),
                '1'   => $this->translate('My Agents should use DNS to look up Endpoint names'),
            ],
            'value'  => $settings->getStoredOrDefaultValue('self-service/resolve_parent_host')
        ]);

        $this->addElement('extensibleSet', 'global_zones', [
            'label'       => $this->translate('Global Zones'),
            'description' => $this->translate(
                'To ensure downloaded packages are build by the Icinga Team'
                . ' and not compromised by third parties, you will be able'
                . ' to provide an array of SHA1 hashes here. In case you have'
                . ' defined any hashses, the module will not continue with'
                . ' updating / installing the Agent in case the SHA1 hash of'
                . ' the downloaded MSI package is not matching one of the'
                . ' provided hashes of this setting'
            ),
            'multiOptions' => $this->enumGlobalZones(),
            'value'  => $settings->getStoredOrDefaultValue('self-service/global_zones'),
        ]);

        $this->addElement('select', 'download_type', [
            'label'       => $this->translate('Installation Source'),
            'description' => $this->translate(
                'You might want to let the generated Powershell script install'
                . ' the Icinga 2 Agent in an automated way. If so, please choose'
                . ' where your Windows nodes should fetch the Agent installer'
            ),
            'multiOptions' => [
                ''         => $this->translate('- no automatic installation -'),
                // TODO: not yet
                // 'director' => $this->translate('Download via the Icinga Director'),
                'icinga'   => $this->translate('Download from packages.icinga.com'),
                'url'      => $this->translate('Download from a custom url'),
                'file'     => $this->translate('Use a local file or network share'),
            ],
            'value'  => $settings->getStoredOrDefaultValue('self-service/download_type'),
            'class' => 'autosubmit'
        ]);

        $downloadType = $this->getSentValue(
            'download_type',
            $settings->getStoredOrDefaultValue('self-service/download_type')
        );

        if ($downloadType) {
            $this->addInstallSettings($downloadType, $settings);
        }

        $this->addEventuallyConfiguredBoolean('flush_api_dir', [
            'label'       => $this->translate('Flush API directory'),
            'description' => $this->translate(
                'In case the Icinga Agent will accept configuration from the parent'
                . ' Icinga 2 system, it will possibly write data to /var/lib/icinga2/api/*.'
                . ' By setting this parameter to true, all content inside the api directory'
                . ' will be flushed before an eventual restart of the Icinga 2 Agent'
            ),
            'required' => true,
        ]);
    }

    protected function addInstallSettings($downloadType, Settings $settings)
    {
        $this->addElement('text', 'download_url', [
            'label'       => $this->translate('Source Path'),
            'description' => $this->translate(
                'Define a download Url or local directory from which the a specific'
                . ' Icinga 2 Agent MSI Installer package should be fetched. Please'
                . ' ensure to only define the base download Url or Directory. The'
                . ' Module will generate the MSI file name based on your operating'
                . ' system architecture and the version to install. The Icinga 2 MSI'
                . ' Installer name is internally build as follows:'
                . ' Icinga2-v[InstallAgentVersion]-[OSArchitecture].msi (full example:'
                . ' Icinga2-v2.6.3-x86_64.msi)'
            ),
            'value'  => $settings->getStoredOrDefaultValue('self-service/download_url'),
        ]);

        // TODO: offer to check for available versions
        if ($downloadType === 'icinga') {
            $el = $this->getElement('download_url');
            $el->setAttrib('disabled', 'disabled');
            $value = 'https://packages.icinga.com/windows/';
            $el->setValue($value);
            $this->setSentValue('download_url', $value);
        }
        if ($downloadType === 'director') {
            $el = $this->getElement('download_url');
            $el->setAttrib('disabled', 'disabled');

            $r = $this->getRequest();
            $scheme = $r->getServer('HTTP_X_FORWARDED_PROTO', $r->getScheme());

            $value = sprintf(
                '%s://%s%s/director/download/windows/',
                $scheme,
                $r->getHttpHost(),
                $this->getRequest()->getBaseUrl()
            );
            $el->setValue($value);
            $this->setSentValue('download_url', $value);
        }

        $this->addElement('text', 'agent_version', [
            'label'       => $this->translate('Agent Version'),
            'description' => $this->translate(
                'In case the Icinga 2 Agent should be automatically installed,'
                . ' this has to be a string value like: 2.6.3'
            ),
            'value'  => $settings->getStoredOrDefaultValue('self-service/agent_version'),
            'required' => true,
        ]);

        $hashes = $settings->getStoredOrDefaultValue('self-service/installer_hashes');
        $this->addElement('extensibleSet', 'installer_hashes', [
            'label'       => $this->translate('Installer Hashes'),
            'description' => $this->translate(
                'To ensure downloaded packages are build by the Icinga Team'
                . ' and not compromised by third parties, you will be able'
                . ' to provide an array of SHA1 hashes here. In case you have'
                . ' defined any hashses, the module will not continue with'
                . ' updating / installing the Agent in case the SHA1 hash of'
                . ' the downloaded MSI package is not matching one of the'
                . ' provided hashes of this setting'
            ),
            'value'  => $hashes,
        ]);

        $this->addElement('text', 'icinga_service_user', [
            'label'       => $this->translate('Service User'),
            'description' => $this->translate(
                'The user that should run the Icinga 2 service on Windows.'
            ),
            'value'  => $settings->getStoredOrDefaultValue('self-service/icinga_service_user'),
        ]);

        $this->addEventuallyConfiguredBoolean('allow_updates', [
            'label'        => $this->translate('Allow Updates'),
            'description' => $this->translate(
                'In case the Icinga 2 Agent is already installed on the system,'
                . ' this parameter will allow you to configure if you wish to'
                . ' upgrade / downgrade to a specified version with the as well.'
            ),
            'required' => true,
        ]);

        $this->addNscpSettings();
    }

    protected function addNscpSettings()
    {
        $this->addEventuallyConfiguredBoolean('install_nsclient', [
            'label'       => $this->translate('Install NSClient++'),
            'description' => $this->translate(
                'Also install NSClient++. It can be used through the Icinga Agent'
                . ' and comes with a bunch of additional Check Plugins'
            ),
            'required' => true,
        ]);
        /*
         * TODO: eventually add those:
        if ($settings->get('self-service/install_nsclient') === 'y') {
            $params['install_nsclient'] = true;
            $this->addBooleanSettingsToParams($settings, [
                'nsclient_add_defaults',
                'nsclient_firewall',
                'nsclient_service',
            ], $params);


            $this->addStringSettingsToParams($settings, [
                'nsclient_directory',
                'nsclient_installer_path'
            ], $params);
        }
        */
    }

    public static function create(Db $db, Settings $settings)
    {
        return static::load()->setDb($db)->setSettings($settings);
    }

    protected function addEventuallyConfiguredBoolean($name, $params)
    {
        $key = "self-service/$name";
        $value = $this->settings->getStoredValue($key);
        $params['value'] = $value;
        $params['multiOptions'] = $this->eventuallyConfiguredEnum($name, [
            'y' => $this->translate('Yes'),
            'n' => $this->translate('No'),
        ]);

        return $this->addElement('select', $name, $params);
    }

    protected function eventuallyConfiguredEnum($name, $enum)
    {
        $key = "self-service/$name";
        $default = $this->settings->getDefaultValue($key);
        if ($default === null) {
            return [
                '' => $this->translate('- please choose -')
            ] + $enum;
        } else {
            return [
                '' => sprintf($this->translate('%s (default)'), $enum[$default])
            ] + $enum;
        }
    }

    protected function setSentValue($key, $value)
    {
        $this->getRequest()->setPost($key, $value);
        return $this;
    }

    protected function enumGlobalZones()
    {
        $db = $this->getDb()->getDbAdapter();
        $zones = $db->fetchCol(
            $db->select()->from('icinga_zone', 'object_name')
                ->where('disabled = ?', 'n')
                ->where('is_global = ?', 'y')
                ->order('object_name')
        );

        return array_combine($zones, $zones);
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

                $this->settings->set("self-service/$key", $value);
            }

            $this->setSuccessMessage($this->translate(
                'Self Service Settings have been stored'
            ));

            parent::onSuccess();
        } catch (Exception $e) {
            $this->addException($e);
        }
    }
}
