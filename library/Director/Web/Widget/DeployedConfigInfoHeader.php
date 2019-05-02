<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\HtmlDocument;
use Icinga\Module\Director\Core\DeploymentApiInterface;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\DeployConfigForm;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\NameValueTable;

class DeployedConfigInfoHeader extends HtmlDocument
{
    use TranslationHelper;

    /** @var IcingaConfig */
    protected $config;

    /** @var int */
    protected $deploymentId;

    /** @var Db */
    protected $db;

    /** @var DeploymentApiInterface */
    protected $api;

    public function __construct(
        IcingaConfig $config,
        Db $db,
        DeploymentApiInterface $api,
        $deploymentId = null
    ) {
        $this->config = $config;
        $this->db     = $db;
        $this->api    = $api;
        if ($deploymentId) {
            $this->deploymentId = (int) $deploymentId;
        }
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Zend_Form_Exception
     */
    protected function assemble()
    {
        $config = $this->config;
        $deployForm = DeployConfigForm::load()
            ->setDb($this->db)
            ->setApi($this->api)
            ->setChecksum($config->getHexChecksum())
            ->setDeploymentId($this->deploymentId)
            ->setAttrib('class', 'inline')
            ->handleRequest();

        $links = new NameValueTable();
        $links->addNameValueRow(
            $this->translate('Actions'),
            [
                $deployForm,
                Html::tag('br'),
                Link::create(
                    $this->translate('Last related activity'),
                    'director/config/activity',
                    ['checksum' => $config->getLastActivityHexChecksum()],
                    ['class' => 'icon-clock', 'data-base-target' => '_next']
                ),
                Html::tag('br'),
                Link::create(
                    $this->translate('Diff with other config'),
                    'director/config/diff',
                    ['left' => $config->getHexChecksum()],
                    ['class' => 'icon-flapping', 'data-base-target' => '_self']
                )
            ]
        )->addNameValueRow(
            $this->translate('Statistics'),
            sprintf(
                $this->translate('%d files rendered in %0.2fs'),
                count($config->getFiles()),
                $config->getDuration() / 1000
            )
        );

        $this->add($links);
    }
}
