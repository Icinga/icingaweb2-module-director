<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Application\Config;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Web\Notification;

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
        $keptValuesCount = $this->snapshot->restoreTo($this->getDb());
        $this->setSuccessUrl($this->getSuccessUrl()->with('target_db', $this->getValue('target_db')));
        $this->setSuccessMessage(sprintf('Restored to %s', $this->getValue('target_db')));

        if ($keptValuesCount > 0) {
            // The schema still restored fine, only some values couldn't follow.
            // Either an old Data Field still owns them, or a rename's new key
            // was already taken. Adds up every kept or dropped value into one
            // number.
            Notification::warning(sprintf(
                $this->translate(
                    '%d stored value(s) were kept under their old name or dropped in'
                    . ' this basket, either a Data Field still owns the name, or a'
                    . ' renamed value\'s new key was already taken.'
                ),
                $keptValuesCount
            ));
        }

        parent::onSuccess();
    }
}
