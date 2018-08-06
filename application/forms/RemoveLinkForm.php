<?php

namespace Icinga\Module\Director\Forms;

use dipl\Html\Icon;
use dipl\Web\Url;
use Icinga\Module\Director\Web\Form\DirectorForm;

class RemoveLinkForm extends DirectorForm
{
    private $label;

    private $title;

    public function __construct($label, $title, $action, $params = [])
    {
        parent::__construct([
            'style' => 'float: right'
        ]);
        $this->label = $label;
        $this->title = $title;
        foreach ($params as $name => $value) {
            $this->addHidden($name, $value);
        }
        $this->setAction($action);
    }

    public function setup()
    {
        $this->setAttrib('class', 'inline');
        //$this->setDecorators(['Form', 'FormElements']);
//                     'class' => 'icon-cancel',
//                    'style' => 'float: right; font-weight: normal',
//                    'title' => $this->translate('Remove this set from this host')

        $this->addHtml(Icon::create('cancel'));
        $this->addSubmitButton($this->label, [
            'class'            => 'link-button',
            'title'            => $this->title,
            'data-base-target' => '_next'
        ]);
    }

    public function onSuccess()
    {
        // nothing.
    }
}
