<?php

namespace Icinga\Module\Director\Web;

use Exception;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Forms\IcingaForgetApiKeyForm;
use Icinga\Module\Director\Forms\IcingaGenerateApiKeyForm;
use Icinga\Application\Icinga;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\IcingaConfig\AgentWizard;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Util;
use ipl\Html\Html;
use ipl\Html\Link;
use ipl\Translation\TranslationHelper;
use ipl\Web\Widget\ActionBar;
use ipl\Web\Widget\ControlsAndContent;

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

    public function renderTo(ControlsAndContent $controller)
    {
        $host = $this->host;
        if ($host->isTemplate()) {
            $this->showSelfServiceTemplateInstructions($controller);
        } elseif ($key = $host->getProperty('api_key')) {
            $this->showRegisteredAgentInstructions($controller);
        } elseif ($key = $host->getSingleResolvedProperty('api_key')) {
            $this->showNewAgentInstructions($controller);
        } else {
            $this->showLegacyAgentInstructions($controller);
        }
    }

    protected function showRegisteredAgentInstructions(ControlsAndContent $c)
    {
        $c->addTitle($this->translate('Registered Agent'));
        $c->content()->add([
            Html::p($this->translate(
                'This host has been registered via the Icinga Director Self Service'
                . " API. In case you re-installed the host or somehow lost it's"
                . ' secret key, you might want to dismiss the current key. This'
                . ' would allow you to register the same host again.'
            )),
            Html::p(['class' => 'warning'], $this->translate(
                'It is not a good idea to do so as long as your Agent still has'
                . ' a valid Self Service API key!'
            )),
            IcingaForgetApiKeyForm::load()->setHost($this->host)->handleRequest()
        ]);
    }

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

        if (Icinga::app()->getModuleManager()->hasLoaded('doc')) {
            $actions->add(Link::create(
                $this->translate('Documentation'),
                'doc/module/director/chapter/Self-Service-API',
                null,
                ['class' => 'icon-book']
            ));
        }

        if ($hasKey) {
            $wizard = new AgentWizard($host);

            $c->add([
                Html::p([$this->translate('Api Key:'), ' ', Html::strong($key)]),
                Html::pre(
                    ['class' => 'logfile'],
                    $wizard->renderTokenBasedWindowsInstaller($key)
                ),
                Html::h2($this->translate('Generate a new key')),
                Html::p(['class' => 'warning'], $this->translate(
                    'This will invalidate the former key'
                )),
            ]);
        }

        $c->add([
            // Html::p($this->translate('..')),
            IcingaGenerateApiKeyForm::load()->setHost($host)->handleRequest()
        ]);
        if ($hasKey) {
            $c->add([
                Html::h2($this->translate('Stop sharing this Template')),
                Html::p($this->translate(
                    'You can stop sharing a Template at any time. This will'
                    . ' immediately invalidate the former key.'
                )),
                IcingaForgetApiKeyForm::load()->setHost($host)->handleRequest()
            ]);
        }
    }

    protected function showNewAgentInstructions(ControlsAndContent $cc)
    {
        $c = $cc->content();
        $host = $this->host;
        $key = $host->getSingleResolvedProperty('api_key');
        $cc->addTitle($this->translate('Configure this Agent  via Self Service API'));

        if (Icinga::app()->getModuleManager()->hasLoaded('doc')) {
            $actions = $cc->actions();
            $actions->add(Link::create(
                $this->translate('Documentation'),
                'doc/module/director/chapter/Self-Service-API',
                null,
                ['class' => 'icon-book']
            ));
        }

        $wizard = new AgentWizard($host);

        $c->add([
            Html::h2('Microsoft Windows'),
            Html::pre(
                ['class' => 'logfile'],
                $wizard->renderTokenBasedWindowsInstaller($key)
            )
        ]);
    }

    protected function showLegacyAgentInstructions(ControlsAndContent $cc)
    {
        $host = $this->host;
        $c = $cc->content();
        $docBaseUrl = 'https://docs.icinga.com/icinga2/latest/doc/module/icinga2/chapter/distributed-monitoring';
        $sectionSetup = 'distributed-monitoring-setup-satellite-client';
        $sectionTopDown = 'distributed-monitoring-top-down';
        $c->add(Html::p()->addPrintf(
            'Please check the %s for more related information.'
            . ' The Director-assisted setup corresponds to configuring a %s environment.',
            Html::a(
                ['href' => $docBaseUrl . '#' . $sectionSetup],
                $this->translate('Icinga 2 Client documentation')
            ),
            Html::a(
                ['href' => $docBaseUrl . '#' . $sectionTopDown],
                $this->translate('Top Down')
            )
        ));

        $cc->addTitle('Agent deployment instructions');
        $certname = $host->getObjectName();

        try {
            $ticket = Util::getIcingaTicket($certname, $this->api->getTicketSalt());
            $wizard = new AgentWizard($host);
            $wizard->setTicketSalt($this->api->getTicketSalt());
        } catch (Exception $e) {
            $c->add(Html::p(['class' => 'error'], sprintf(
                $this->translate(
                    'A ticket for this agent could not have been requested from'
                    . ' your deployment endpoint: %s'
                ),
                $e->getMessage()
            )));

            return;
        }

        // TODO: move to CSS
        $codeStyle = ['style' => 'background: black; color: white; height: 14em; overflow: scroll;'];
        $c->add([
            Html::h2($this->translate('For manual configuration')),
            Html::p($this->translate('Ticket'), ': ', Html::code($ticket)),
            Html::h2($this->translate('Windows Kickstart Script')),
            Link::create(
                $this->translate('Download'),
                $cc->url()->with('download', 'windows-kickstart'),
                null,
                ['class' => 'icon-download', 'target' => '_blank']
            ),
            Html::pre($codeStyle, $wizard->renderWindowsInstaller()),
            Html::p($this->translate(
                'This requires the Icinga Agent to be installed. It generates and signs'
                . ' it\'s certificate and it also generates a minimal icinga2.conf to get'
                . ' your agent connected to it\'s parents'
            )),
            Html::h2($this->translate('Linux commandline')),
            Link::create(
                $this->translate('Download'),
                $cc->url()->with('download', 'linux'),
                null,
                ['class' => 'icon-download', 'target' => '_blank']
            ),
            Html::p($this->translate('Just download and run this script on your Linux Client Machine:')),
            Html::pre($codeStyle, $wizard->renderLinuxInstaller())
        ]);
    }

    public function handleLegacyAgentDownloads($os)
    {
        $wizard = new AgentWizard($this->host);
        $wizard->setTicketSalt($this->api->getTicketSalt());

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
}
