<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Core\DeploymentApiInterface;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
// use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Form\DirectorForm;
use Icinga\Module\Director\Web\Form\QuickForm;

class DeployConfigForm extends DirectorForm
{
    /** @var DeploymentApiInterface */
    private $api;

    /** @var string */
    private $checksum;

    /** @var int */
    private $deploymentId;

    public function init()
    {
        $this->setAttrib('class', 'inline');
    }

    public function setup()
    {
        $activities = $this->db->countActivitiesSinceLastDeployedConfig();
        if ($this->deploymentId) {
            $label = $this->translate('Re-deploy now');
        } elseif ($activities === 0) {
            $label = $this->translate('There are no pending changes. Deploy anyway');
        } else {
            $label = sprintf(
                $this->translate('Deploy %d pending changes'),
                $activities
            );
        }

        if ($this->deploymentId) {
            $deployIcon = 'reply-all';
        } else {
            $deployIcon = 'forward';
        }

        $this->addHtml(
            $this->getView()->icon(
                $deployIcon,
                $label,
                array('class' => 'link-color')
            ) . '<nobr>'
        );

        $el = $this->createElement('submit', 'btn_deploy', array(
            'label' => $label,
            'escape' => false,
            'decorators' => array('ViewHelper'),
            'class' => 'link-button ' . $deployIcon,
            ));

        $this->addHtml('</nobr>');
        $this->submitButtonName = $el->getName();
        $this->setSubmitLabel($label);
        $this->addElement($el);
    }

    public function onSuccess()
    {
        $db = $this->db;
        $checksum = $this->checksum;
        $msg = $this->translate('Config has been submitted, validation is going on');
        $this->setSuccessMessage($msg);

        $isApiRequest = $this->getRequest()->isApiRequest();
        if ($this->checksum) {
            $config = IcingaConfig::load(hex2bin($this->checksum), $db);
        } else {
            $config = IcingaConfig::generate($db);
        }

        $this->api->wipeInactiveStages($db);

        if ($this->api->dumpConfig($config, $db)) {
            if ($isApiRequest) {
                die('Api not ready');
               //  return $this->sendJson((object) array('checksum' => $checksum));
            } else {
                $this->setSuccessUrl('director/config/deployments');
                $this->setSuccessMessage(
                    $this->translate('Config has been submitted, validation is going on')
                );
            }
            parent::onSuccess();
        } else {
            throw new IcingaException($this->translate('Config deployment failed'));
        }
    }

    public function setChecksum($checksum)
    {
        $this->checksum = $checksum;
        return $this;
    }

    public function setDeploymentId($id)
    {
        $this->deploymentId = $id;
        return $this;
    }

    public function setApi(DeploymentApiInterface $api)
    {
        $this->api = $api;
        return $this;
    }
}
