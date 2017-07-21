<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Forms\IcingaHostSelfServiceForm;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Settings;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ActionController;
use ipl\Html\Html;

class SelfServiceController extends ActionController
{
    /** @var bool */
    protected $isApified = true;

    /** @var bool */
    protected $requiresAuthentication = false;

    /** @var Settings */
    protected $settings;

    protected function assertApiPermission()
    {
        // no permission required, we'll check the API key
    }

    protected function checkDirectorPermissions()
    {
    }

    public function apiVersionAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->sendPowerShellResponse('1.4.0');
        } else {
            throw new NotFoundError('Not found');
        }
    }

    public function registerHostAction()
    {
        $request = $this->getRequest();
        $form = IcingaHostSelfServiceForm::create($this->db());
        $form->setApiRequest($request->isApiRequest());
        try {
            if ($key = $this->params->get('key')) {
                $form->loadTemplateWithApiKey($key);
            }
        } catch (Exception $e) {
            $this->sendPowerShellError($e->getMessage(), 404);
            return;
        }
        if ($name = $this->params->get('name')) {
            $form->setHostName($name);
        }

        if ($request->isApiRequest()) {
            $data = json_decode($request->getRawBody());
            $request->setPost((array) $data);
            $form->handleRequest();
            if ($newKey = $form->getHostApiKey()) {
                $this->sendPowerShellResponse($newKey);
            } else {
                $error = implode('; ', $form->getErrorMessages());
                if ($error === '') {
                    foreach ($form->getErrors() as $elName => $errors) {
                        if (in_array('isEmpty', $errors)) {
                            $this->sendPowerShellError(
                                sprintf("%s is required", $elName),
                                400
                            );
                            return;
                        } else {
                            $this->sendPowerShellError('An unknown error ocurred', 500);
                        }
                    }
                } else {
                    $this->sendPowerShellError($error, 400);
                }
            }
            return;
        }

        $form->handleRequest();
        $this->addSingleTab($this->translate('Self Service'))
            ->addTitle($this->translate('Self Service - Host Registration'))
            ->content()->add(Html::p($this->translate(
                'In case an Icinga Admin provided you with a self service API'
                . ' token, this is where you can register new hosts'
            )))
            ->add($form);
    }

    public function ticketAction()
    {
        if (!$this->getRequest()->isApiRequest()) {
            throw new NotFoundError('Not found');
        }

        try {
            $key = $this->params->getRequired('key');
            $host = IcingaHost::loadWithApiKey($key, $this->db());
            if ($host->isTemplate()) {
                throw new NotFoundError('Got invalid API key "%s"', $key);
            }
            $name = $host->getObjectName();

            if ($host->getResolvedProperty('has_agent') !== 'y') {
                throw new NotFoundError('The host "%s" is not an agent', $name);
            }

            $this->sendPowerShellResponse(
                Util::getIcingaTicket(
                    $name,
                    $this->api()->getTicketSalt()
                )
            );
        } catch (Exception $e) {
            if ($e instanceof NotFoundError) {
                $this->sendPowerShellError($e->getMessage(), 404);
            } else {
                $this->sendPowerShellError($e->getMessage(), 500);
            }
        }
    }

    protected function sendPowerShellResponse($response)
    {
        if ($this->getRequest()->getHeader('X-Director-Accept') === 'text/plain') {
            if (is_array($response)) {
                echo $this->makePlainTextPowerShellArray($response);
            } else {
                echo $response;
            }
        } else {
            $this->sendJson($this->getResponse(), $response);
        }
    }

    protected function sendPowerShellError($error, $code)
    {
        $this->getResponse()->setHttpResponseCode($code);
        if ($this->getRequest()->getHeader('X-Director-Accept') === 'text/plain') {
            echo "ERROR: $error";
        } else {
            $this->sendJsonError($this->getResponse(), $error);
        }
    }

    protected function makePowerShellBoolean($value)
    {
        if ($value === 'y' || $value === true) {
            return 'true';
        } elseif ($value === 'n' || $value === false) {
            return 'false';
        } else {
            throw new ProgrammingError(
                'Expected boolean value, got %s',
                var_export($value, 1)
            );
        }
    }

    protected function makePlainTextPowerShellArray(array $params)
    {
        $plain = '';

        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $value = $this->makePowerShellBoolean($value);
            } elseif (is_array($value)) {
                $value = implode('!', $value);
            }
            $plain .= "$key: $value\r\n";
        }

        return $plain;
    }

    public function powershellParametersAction()
    {
        if (!$this->getRequest()->isApiRequest()) {
            throw new NotFoundError('Not found');
        }

        try {
            $this->shipPowershellParams();
        } catch (Exception $e) {
            if ($e instanceof NotFoundError) {
                $this->sendPowerShellError($e->getMessage(), 404);
            } else {
                $this->sendPowerShellError($e->getMessage(), 500);
            }
        }
    }

    protected function shipPowershellParams()
    {
        $db = $this->db();
        $key = $this->params->getRequired('key');
        $host = IcingaHost::loadWithApiKey($key, $db);

        $settings = $this->getSettings();
        $transform = $settings->get('self-service/transform_hostname');
        $params = [
            'fetch_agent_name'    => $settings->get('self-service/agent_name') === 'hostname',
            'fetch_agent_fqdn'    => $settings->get('self-service/agent_name') === 'fqdn',
            'transform_hostname'  => $transform,
            'flush_api_directory' => $settings->get('self-service/flush_api_dir') === 'y'
        ];

        if ($transform === '2') {
            $transformMethod = '.upperCase';
        } elseif ($transform === '1') {
            $transformMethod = '.lowerCase';
        } else {
            $transformMethod = '';
        }

        $hostObject = (object) [
            'address' => '&ipaddress&',
        ];

        switch ($settings->get('self-service/agent_name')) {
            case 'hostname':
                $hostObject->display_name = "&fqdn$transformMethod&";
                break;
            case 'fqdn':
                $hostObject->display_name = "&hostname$transformMethod&";
                break;
        }
        $params['director_host_object'] = json_encode($hostObject);

        if ($settings->get('self-service/download_type')) {
            $params['download_url'] = $settings->get('self-service/download_url');
            $params['agent_version'] = $settings->get('self-service/agent_version');
            $params['allow_updates'] = $settings->get('self-service/allow_updates') === 'y';
            $params['agent_listen_port'] = $host->getAgentListenPort();
            if ($hashes = $settings->get('self-service/installer_hashes')) {
                $params['installer_hashes'] = $hashes;
            }

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
        }

        $this->addHostToParams($host, $params);

        if ($this->getRequest()->getHeader('X-Director-Accept') === 'text/plain') {
            echo $this->makePlainTextPowerShellArray($params);
        } else {
            $this->sendJson($this->getResponse(), $params);
        }
    }

    protected function addHostToParams(IcingaHost $host, array & $params)
    {
        if (! $host->isObject()) {
            return;
        }

        $db = $this->db();
        $settings = $this->getSettings();
        $name = $host->getObjectName();
        if ($host->getSingleResolvedProperty('has_agent') !== 'y') {
            $this->sendPowerShellError(sprintf(
                '%s is not configured for Icinga Agent usage',
                $name
            ), 403);
            return;
        }

        $zoneName = $host->getRenderingZone();
        if ($zoneName === IcingaHost::RESOLVE_ERROR) {
            $this->sendPowerShellError(sprintf(
                'Could not resolve target Zone for %s',
                $name
            ), 404);
            return;
        }

        $masterConnectsToAgent = $host->getSingleResolvedProperty(
            'master_should_connect'
        ) === 'y';
        $params['agent_add_firewall_rule'] = $masterConnectsToAgent;

        $params['global_zones'] = $settings->get('self-service/global_zones');

        $zone = IcingaZone::load($zoneName, $db);
        $endpointNames = $zone->listEndpoints();
        if (! $masterConnectsToAgent) {
            $endpointsConfig = [];
            foreach ($endpointNames as $endpointName) {
                $endpoint = IcingaEndpoint::load($endpointName, $db);
                $endpointsConfig[] = sprintf(
                    '%s;%s',
                    $endpoint->getSingleResolvedProperty('host'),
                    $endpoint->getResolvedPort()
                );
            }

            $params['endpoints_config'] = $endpointsConfig;
        }
        $master = $db->getDeploymentEndpoint();
        $params['parent_zone']      = $zoneName;
        $params['ca_server']        = $master->getObjectName();
        $params['parent_endpoints'] = $endpointNames;
        $params['accept_config']    = $host->getSingleResolvedProperty('accept_config')=== 'y';
    }

    protected function addStringSettingsToParams(Settings $settings, array $keys, array & $params)
    {
        foreach ($keys as $key) {
            $value = $settings->get("self-service/$key");
            if (strlen($value)) {
                $params[$key] = $value;
            }
        }
    }

    protected function addBooleanSettingsToParams(Settings $settings, array $keys, array & $params)
    {
        foreach ($keys as $key) {
            $value = $settings->get("self-service/$key");
            if ($value !== null) {
                $params[$key] = $value === 'y';
            }
        }
    }

    protected function getSettings()
    {
        if ($this->settings === null) {
            $this->settings = new Settings($this->db());
        }

        return $this->settings;
    }
}
