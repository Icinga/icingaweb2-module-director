<?php

namespace Icinga\Module\Director\Web\Form;

use dipl\Html\Form;
use Icinga\Web\Window;

class DbSelectorForm extends Form
{
    protected $defaultAttributes = [
        'class' => 'db-selector'
    ];

    protected $allowedNames;

    /** @var Window */
    protected $window;

    public function __construct(Window $window, $allowedNames)
    {
        $this->window = $window;
        $this->allowedNames = $allowedNames;
    }

    protected function assemble()
    {
        $this->addElement('DbSelector', 'hidden', [
            'value' => 'sent'
        ]);
        $this->addElement('db_resource', 'select', [
            'options' => $this->allowedNames,
            'class'   => 'autosubmit',
            'value'   => $this->getSession()->get('db_resource')
        ]);
    }

    /**
     * A base class should handle this, based on hidden fields
     *
     * @return bool
     */
    public function hasBeenSubmitted()
    {
        return $this->hasBeenSent() && $this->getRequest()->get('DbSelector') === 'sent';
    }

    public function onSuccess()
    {
        $this->getSession()->set('db_resource', $this->getValue('db_resource'));
        $this->redirectOnSuccess();
    }

    /**
     * @return \Icinga\Web\Session\SessionNamespace
     */
    protected function getSession()
    {
        return $this->window->getSessionNamespace('director');
    }
}
