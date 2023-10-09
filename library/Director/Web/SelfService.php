<?php

namespace Icinga\Module\Director\Web;

use Exception;
use gipfl\Web\Widget\Hint;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Forms\IcingaForgetApiKeyForm;
use Icinga\Module\Director\Forms\IcingaGenerateApiKeyForm;
use Icinga\Application\Icinga;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\IcingaConfig\AgentWizard;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Widget\Documentation;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\ActionBar;
use gipfl\IcingaWeb2\Widget\ControlsAndContent;

class SelfService
{
    use TranslationHelper;

    /** @var IcingaHost */
    protected $host;

    /** @var CoreApi */
    protected $api;

    public function __construct(IcingaHost $host, CoreApi $api)
    {
        $this->host = $host;
        $this->api = $api;
    }

    /**
     * @param ControlsAndContent $controller
     */
    public function renderTo(ControlsAndContent $controller)
    {
        $host = $this->host;
        if ($host->isTemplate()) {
            $this->showSelfServiceTemplateInstructions($controller);
        } elseif ($key = $host->getProperty('api_key')) {
            $this->showRegisteredAgentInstructions($key, $controller);
        } elseif ($key = $host->getSingleResolvedProperty('api_key')) {
            $this->showNewAgentInstructions($controller);
        } else {
            $this->showLegacyAgentInstructions($controller);
        }
    }

    /**
     * @param string $key
     * @param ControlsAndContent $c
     */
    protected function showRegisteredAgentInstructions($key, ControlsAndContent $c)
    {
        $c->addTitle($this->translate('Registered Agent'));
        $c->content()->add([
            Html::tag('p', null, $this->translate(
                'This host has been registered via the Icinga Director Self Service'
                . " API. In case you re-installed the host or somehow lost it's"
                . ' secret key, you might want to dismiss the current key. This'
                . ' would allow you to register the same host again.'
            )),
            Html::tag('p', null, [$this->translate('Api Key:'), ' ', Html::tag('strong', null, $key)]),
            Hint::warning($this->translate(
                'It is not a good idea to do so as long as your Agent still has'
                . ' a valid Self Service API key!'
            )),
            IcingaForgetApiKeyForm::load()->setHost($this->host)->handleRequest()
        ]);
    }

    /**
     * @param ControlsAndContent $cc
     */
    protected function showSelfServiceTemplateInstructions(ControlsAndContent $cc)
    {
        $host = $this->host;
        $key = $host->getProperty('api_key');
        $hasKey = $key !== null;
        if ($hasKey) {
            $cc->addTitle($this->translate('Shared for Self Service API'));
        } else {
            $cc->addTitle($this->translate('Share this Template for Self Service API'));
        }

        $c = $cc->content();
        /** @var ActionBar $actions */
        $actions = $cc->actions();
        $actions->setBaseTarget('_next')->add(Link::create(
            $this->translate('Settings'),
            'director/settings/self-service',
            null,
            [
                'title' => $this->translate('Global Self Service Setting'),
                'class' => 'icon-services',
            ]
        ));

        $actions->add($this->getDocumentationLink());

        if ($hasKey) {
            $c->add([
                Html::tag('p', [
                    $this->translate('Api Key:'), ' ', Html::tag('strong', null, $key)
                ]),
                $this->getWindowsInstructions($host, $key),
                Html::tag('h2', null, $this->translate('Generate a new key')),
                Hint::warning($this->translate(
                    'This will invalidate the former key'
                )),
            ]);
        }

        $c->add([
            // Html::tag('p', null, $this->translate('..')),
            IcingaGenerateApiKeyForm::load()->setHost($host)->handleRequest()
        ]);
        if ($hasKey) {
            $c->add([
                Html::tag('h2', null, $this->translate('Stop sharing this Template')),
                Html::tag('p', null, $this->translate(
                    'You can stop sharing a Template at any time. This will'
                    . ' immediately invalidate the former key.'
                ) . ' ' . $this->translate(
                    'Generated Host keys will continue to work, but you\'ll no'
                    . ' longer be able to register new Hosts with this key'
                )),
                IcingaForgetApiKeyForm::load()->setHost($host)->handleRequest()
            ]);
        }
    }

    protected function getWindowsInstructions($host, $key)
    {
        $wizard = new AgentWizard($host);

        return [
            Html::tag('h2', $this->translate('Icinga for Windows')),
            Html::tag('p', Html::sprintf(
                $this->translate('In case you\'re using %s, please run this Script:'),
                Html::tag('a', [
                    'href'   => 'https://icinga.com/docs/windows/latest/',
                    'target' => '_blank',
                ], $this->translate('Icinga for Windows'))
            )),
            Html::tag(
                'pre',
                ['class' => 'logfile'],
                $wizard->renderIcinga4WindowsWizardCommand($key)
            ),
        ];
    }

    protected function getDocumentationLink()
    {
        return Documentation::link(
            $this->translate('Documentation'),
            'director',
            '74-Self-Service-API',
            $this->translate('Self Service API')
        );
    }

    /**
     * @param ControlsAndContent $cc
     */
    protected function showNewAgentInstructions(ControlsAndContent $cc)
    {
        $content = $cc->content();
        $host = $this->host;
        $key = $host->getSingleResolvedProperty('api_key');
        $cc->addTitle($this->translate('Configure this Agent via Self Service API'));
        $cc->actions()->add($this->getDocumentationLink());
        $content->add(Html::tag('p', [
            $this->translate('Inherited Template Api Key:'), ' ', Html::tag('strong', null, $key)
        ]));
        $content->add($this->getWindowsInstructions($host, $key));
    }

    /**
     * @param ControlsAndContent $cc
     */
    protected function showLegacyAgentInstructions(ControlsAndContent $cc)
    {
        $host = $this->host;
        $c = $cc->content();
        $docBaseUrl = 'https://docs.icinga.com/icinga2/latest/doc/module/icinga2/chapter/distributed-monitoring';
        $sectionSetup = 'distributed-monitoring-setup-satellite-client';
        $sectionTopDown = 'distributed-monitoring-top-down';
        $c->add(Html::tag('p')->add(Html::sprintf(
            'Please check the %s for more related information.'
            . ' The Director-assisted setup corresponds to configuring a %s environment.',
            Html::tag(
                'a',
                ['href' => $docBaseUrl . '#' . $sectionSetup],
                $this->translate('Icinga 2 Client documentation')
            ),
            Html::tag(
                'a',
                ['href' => $docBaseUrl . '#' . $sectionTopDown],
                $this->translate('Top Down')
            )
        )));

        $cc->addTitle('Agent deployment instructions');

        try {
            $ticket = $this->api->getTicket($host->getEndpointName());
            $wizard = new AgentWizard($host);
            $wizard->setTicket($ticket);
        } catch (Exception $e) {
            $c->add(Hint::error(sprintf(
                $this->translate(
                    'A ticket for this agent could not have been requested from'
                    . ' your deployment endpoint: %s'
                ),
                $e->getMessage()
            )));

            return;
        }

        $class = ['class' => 'agent-deployment-instructions'];
        $c->add([
            Html::tag('h2', null, $this->translate('For manual configuration')),
            Html::tag('p', null, [$this->translate('Ticket'), ': ', Html::tag('code', null, $ticket)]),
            Html::tag('h2', null, $this->translate('Windows Kickstart Script')),
            Link::create(
                $this->translate('Download'),
                $cc->url()->with('download', 'windows-kickstart'),
                null,
                ['class' => 'icon-download', 'target' => '_blank']
            ),
            Html::tag('pre', $class, $wizard->renderWindowsInstaller()),
            Html::tag('p', null, $this->translate(
                'This requires the Icinga Agent to be installed. It generates and signs'
                . ' it\'s certificate and it also generates a minimal icinga2.conf to get'
                . ' your agent connected to it\'s parents'
            )),
            Html::tag('h2', null, $this->translate('Linux commandline')),
            Link::create(
                $this->translate('Download'),
                $cc->url()->with('download', 'linux'),
                null,
                ['class' => 'icon-download', 'target' => '_blank']
            ),

            Html::tag('p', null, $this->translate('Just download and run this script on your Linux Client Machine:')),
            Html::tag('pre', $class, $wizard->renderLinuxInstaller())
        ]);
    }

    /**
     * @param $os
     * @throws NotFoundError
     */
    public function handleLegacyAgentDownloads($os)
    {
        $wizard = new AgentWizard($this->host);
        $wizard->setTicket($this->api->getTicket($this->host->getEndpointName()));

        switch ($os) {
            case 'windows-kickstart':
                $ext = 'ps1';
                $script = preg_replace('/\n/', "\r\n", $wizard->renderWindowsInstaller());
                break;
            case 'linux':
                $ext = 'bash';
                $script = $wizard->renderLinuxInstaller();
                break;
            default:
                throw new NotFoundError('There is no kickstart helper for %s', $os);
        }

        header('Content-type: application/octet-stream');
        header('Content-Disposition: attachment; filename=icinga2-agent-kickstart.' . $ext);
        echo $script;
        exit;
    }

    /**
     * @return bool
     */
    protected function hasDocsModuleLoaded()
    {
        try {
            return Icinga::app()->getModuleManager()->hasLoaded('doc');
        } catch (ProgrammingError $e) {
            return false;
        }
    }
}
