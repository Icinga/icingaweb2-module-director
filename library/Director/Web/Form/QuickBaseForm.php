<?php

namespace Icinga\Module\Director\Web\Form;

use Zend_Form;

abstract class QuickBaseForm extends Zend_Form
{
    /**
     * The Icinga module this form belongs to. Usually only set if the
     * form is initialized through the FormLoader
     */
    protected $icingaModule;

    protected $icingaModuleName;

    public function __construct($options = null)
    {
        parent::__construct($this->handleOptions($options));

        if ($this->icingaModule) {
            $basedir = sprintf(
                '%s/%s/Web/Form',
                $this->icingaModule->getLibDir(),
                ucfirst($this->icingaModuleName)
            );

            $this->addPrefixPaths(array(
                array(
                    'prefix'    => __NAMESPACE__ . '\\Element\\',
                    'path'      => $basedir . '/Element',
                    'type'      => static::ELEMENT
                )
            ));
        }
    }

    public function addHidden($name, $value = null)
    {
        $this->addElement('hidden', $name);
        $el = $this->getElement($name);
        $el->setDecorators(array('ViewHelper'));
        if ($value !== null) {
            $this->setDefault($name, $value);
            $el->setValue($value);
        }
    
        return $this;
    }

    // TODO: Should be an element
    public function addHtmlHint($html, $options = array())
    {
        return $this->addHtml('<div class="hint">' . $html . '</div>', $options);
    }

    public function addHtml($html, $options = array())
    {
        if (array_key_exists('name', $options)) {
            $name = $options['name'];
            unset($options['name']);
        } else {
            $name = '_HINT' . ++$this->hintCount;
        }

        $this->addElement('simpleNote', $name, $options);
        $this->getElement($name)
            ->setValue($html)
            ->setIgnore(true)
            ->setDecorators(array('ViewHelper'));

        return $this;
    }

    public function optionalEnum($enum)
    {
        return array(
            null => $this->translate('- please choose -')
        ) + $enum;
    }

    protected function handleOptions($options = null)
    {
        if ($options === null) {
            return $options;
        }

        if (array_key_exists('icingaModule', $options)) {
            $this->icingaModule = $options['icingaModule'];
            $this->icingaModuleName = $this->icingaModule->getName();
            unset($options['icingaModule']);
        }

        return $options;
    }

    public function setIcingaModule(Module $module)
    {
        $this->icingaModule = $module;
        return $this;
    }

    protected function loadForm($name, Module $module = null)
    {
        if ($module === null) {
            $module = $this->icingaModule;
        }

        return FormLoader::load($name, $module);
    }

    protected function valueIsEmpty($value)
    {
        if (is_array($value)) {
            return empty($value);
        }

        return strlen($value) === 0;
    }

    public function translate($string)
    {
        if ($this->icingaModuleName === null) {
            return t($string);
        } else {
            return mt($this->icingaModuleName, $string);
        }
    }
}
