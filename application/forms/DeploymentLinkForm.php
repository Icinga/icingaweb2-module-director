<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Authentication\Auth;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Core\DeploymentApiInterface;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Deployment\DeploymentInfo;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Web\Form\DirectorForm;
use gipfl\IcingaWeb2\Icon;
use Zend_View_Interface;

class DeploymentLinkForm extends DirectorForm
{
    use DeployFormsBug7530;

    /** @var DeploymentInfo */
    protected $info;

    /** @var Auth */
    protected $auth;

    /** @var DeploymentApiInterface */
    protected $api;

    /** @var Db */
    protected $db;

    /**
     * @param DeploymentInfo $info
     * @param Auth $auth
     * @return static
     */
    public static function create(Db $db, DeploymentInfo $info, Auth $auth, DeploymentApiInterface $api)
    {
        $self = static::load();
        $self->setAuth($auth);
        $self->db = $db;
        $self->info = $info;
        $self->api = $api;
        return $self;
    }

    public function setAuth(Auth $auth)
    {
        $this->auth = $auth;
        return $this;
    }

    public function setup()
    {
        if (! $this->canDeploy()) {
            return;
        }

        $onObject = $this->info->getSingleObjectChanges();
        $total = $this->info->getTotalChanges();

        if ($onObject === 0) {
            if ($total === 1) {
                $msg = $this->translate('There is a single pending change');
            } else {
                $msg = sprintf(
                    $this->translate('There are %d pending changes'),
                    $total
                );
            }
        } elseif ($total === 1) {
            $msg = $this->translate('There has been a single change to this object, nothing else has been modified');
        } elseif ($total === $onObject) {
            $msg = sprintf(
                $this->translate('There have been %d changes to this object, nothing else has been modified'),
                $onObject
            );
        } else {
            $msg = sprintf(
                $this->translate('There are %d pending changes, %d of them applied to this object'),
                $total,
                $onObject
            );
        }

        $this->setAttrib('class', 'inline');
        $this->addHtml(Icon::create('wrench'));
        $target = $this->shouldWarnAboutBug7530() ? '_self' : '_next';
        $this->addSubmitButton($this->translate('Deploy'), [
            'class'            => 'link-button icon-wrench',
            'title'            => $msg,
            'data-base-target' => $target,
        ]);
    }

    protected function canDeploy()
    {
        return $this->auth->hasPermission('director/deploy');
    }

    public function render(Zend_View_Interface $view = null)
    {
        if (! $this->canDeploy()) {
            return '';
        }

        return parent::render($view);
    }

    public function onSuccess()
    {
        if ($this->skipBecauseOfBug7530()) {
            return;
        }
        $this->deploy();
    }

    public function deploy()
    {
        $this->setSuccessUrl('director/config/deployments');
        $config = IcingaConfig::generate($this->db);
        $checksum = $config->getHexChecksum();

        try {
            $this->api->wipeInactiveStages($this->db);
        } catch (\Exception $e) {
            $this->notifyError($e->getMessage());
        }

        if ($this->api->dumpConfig($config, $this->db)) {
            $this->deploymentSucceeded($checksum);
        } else {
            $this->deploymentFailed($checksum);
        }
    }

    protected function deploymentSucceeded($checksum)
    {
        if ($this->getRequest()->isApiRequest()) {
            throw new IcingaException('Not yet');
            // $this->sendJson($this->getResponse(), (object) array('checksum' => $checksum));
        } else {
            $msg = $this->translate('Config has been submitted, validation is going on');
            $this->redirectOnSuccess($msg);
        }
    }

    protected function deploymentFailed($checksum, $error = null)
    {
        $extra = $error ? ': ' . $error: '';

        if ($this->getRequest()->isApiRequest()) {
            throw new IcingaException('Not yet');
            // $this->sendJsonError($this->getResponse(), 'Config deployment failed' . $extra);
        } else {
            $msg = $this->translate('Config deployment failed') . $extra;
            $this->notifyError($msg);
            $this->redirectAndExit('director/config/deployments');
        }
    }
}
