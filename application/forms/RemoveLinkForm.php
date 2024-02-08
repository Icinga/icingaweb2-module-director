<?php

namespace Icinga\Module\Director\Forms;

use gipfl\IcingaWeb2\Icon;
use Icinga\Module\Director\Web\Form\DirectorForm;

class RemoveLinkForm extends DirectorForm
{
    private $label;

    private $title;

    private $onSuccessAction;

    public function __construct($label, $title, $action, $params = [])
    {
        // Required to detect the right instance
        $this->formName = 'RemoveSet' . sha1(json_encode($params));
        parent::__construct(['data-base-target' => '_self']);
        $this->label = $label;
        $this->title = $title;
        foreach ($params as $name => $value) {
            $this->addHidden($name, $value);
        }
        $this->setAction($action);
    }

    public function runOnSuccess($action)
    {
        $this->onSuccessAction = $action;

        return $this;
    }

    public function setup()
    {
        $this->addAttribs(['class' => ['inline', 'remove-link-form']]);
        $this->addHtml(Icon::create('cancel'));
        $this->addSubmitButton($this->label, [
            'class' => 'link-button',
            'title' => $this->title,
        ]);
    }

    public function onSuccess()
    {
        if ($this->onSuccessAction !== null) {
            $func = $this->onSuccessAction;
            $func();
            $this->redirectOnSuccess(
                $this->translate('Service Set has been removed')
            );
        }
    }
}
