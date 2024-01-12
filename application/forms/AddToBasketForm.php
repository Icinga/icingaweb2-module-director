<?php

namespace Icinga\Module\Director\Forms;

use gipfl\Web\Widget\Hint;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use gipfl\IcingaWeb2\Link;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use Icinga\Module\Director\Web\Form\DirectorForm;

class AddToBasketForm extends DirectorForm
{
    private $type = '(has not been set)';

    private $names = [];

    /**
     * @throws \Zend_Form_Exception
     * @throws \Icinga\Exception\NotFoundError
     */
    public function setup()
    {
        $db = $this->getDb()->getDbAdapter();
        $enum = $db->fetchPairs($db->select()->from('director_basket', [
            'a' => 'basket_name',
            'b' => 'basket_name',
        ])->order('basket_name'));

        $basket = null;
        if ($this->hasBeenSent()) {
            $basketName = $this->getSentValue('basket');
            if ($basketName) {
                $basket = Basket::load($basketName, $this->getDb());
            }
        }

        $names = [];
        foreach ($this->names as $name) {
            if (! $basket || ! $basket->hasObject($this->type, $name)) {
                $names[] = $name;
            }
        }
        $this->addHtmlHint(
            (new HtmlDocument())
                ->add(sprintf('The following objects will be added: %s', implode(", ", $names)))
        );
        $this->addElement('select', 'basket', [
            'label'        => $this->translate('Basket'),
            'multiOptions' => $this->optionalEnum($enum),
            'required'     => true,
            'class'        => 'autosubmit',
        ]);

        if (! empty($names)) {
            $this->setSubmitLabel(sprintf(
                $this->translate('Add %s objects'),
                count($names)
            ));
        } else {
            $this->setSubmitLabel($this->translate('Add'));
            $this->addSubmitButtonIfSet();
            $this->getElement($this->submitButtonName)->setAttrib('disabled', true);
        }
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function setNames($names)
    {
        $this->names = $names;

        return $this;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function onSuccess()
    {
        $type = $this->type;
        $basket = Basket::load($this->getValue('basket'), $this->getDb());
        $basketName = $basket->get('basket_name');

        if (empty($this->names)) {
            $this->getElement('basket')->addErrorMessage($this->translate(
                'No object has been chosen'
            ));
        }
        if ($basket->supportsCustomSelectionFor($type)) {
            $basket->addObjects($type, $this->names);
            $basket->store();
            $this->setSuccessMessage(sprintf($this->translate(
                'Configuration objects have been added to the chosen basket "%s"'
            ), $basketName));
            return parent::onSuccess();
        }

        $this->addHtmlHint(Hint::error(Html::sprintf($this->translate(
            'Please check your Basket configuration, %s does not support'
            . ' single "%s" configuration objects'
        ), Link::create(
            $basketName,
            'director/basket',
            ['name' => $basketName],
            ['data-base-target' => '_next']
        ), $type)));

        return false;
    }
}
