<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Application\Config;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Form\QuickForm;

class RestoreBasketForm extends QuickForm
{
    use DirectorDb;

    /** @var BasketSnapshot */
    private $snapshot;

    public function setSnapshot(BasketSnapshot $snapshot)
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    /**
     * @codingStandardsIgnoreStart
     * @return Auth
     */
    protected function Auth()
    {
        return Auth::getInstance();
    }

    /**
     * @return Config
     */
    protected function Config()
    {
        // @codingStandardsIgnoreEnd
        return Config::module('director');
    }

    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $allowedDbs = $this->listAllowedDbResourceNames();
        $this->addElement('select', 'target_db', [
            'label'        => $this->translate('Target DB'),
            'description'  => $this->translate('Restore to this target Director DB'),
            'multiOptions' => $allowedDbs,
            'value'        => $this->getRequest()->getParam('target_db', $this->getFirstDbResourceName()),
            'class'        => 'autosubmit',
        ]);

        $this->setSubmitLabel($this->translate('Restore'));
    }

    public function getDb()
    {
        return Db::fromResourceName($this->getValue('target_db'));
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function onSuccess()
    {
        $this->snapshot->restoreTo($this->getDb());
        $this->setSuccessUrl($this->getSuccessUrl()->with('target_db', $this->getValue('target_db')));
        $this->setSuccessMessage(sprintf('Restored to %s', $this->getValue('target_db')));

        parent::onSuccess();
    }
}
