<?php

namespace Icinga\Module\Director\Web\Form;

abstract class QuickSubForm extends QuickBaseForm
{
    /**
     * Whether or not form elements are members of an array
     * @codingStandardsIgnoreStart
     * @var bool
     */
    protected $_isArray = true;
    // @codingStandardsIgnoreEnd

    /**
     * Load the default decorators
     *
     * @return Zend_Form_SubForm
     */
    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }

        $decorators = $this->getDecorators();
        if (empty($decorators)) {
            $this->addDecorator('FormElements')
                 ->addDecorator('HtmlTag', array('tag' => 'dl'))
                 ->addDecorator('Fieldset')
                 ->addDecorator('DtDdWrapper');
        }
        return $this;
    }
}
