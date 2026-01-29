<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use ipl\Html\Html;
use ipl\Html\ValidHtml;
use Zend_Form;

abstract class QuickBaseForm extends Zend_Form implements ValidHtml
{
    /**
     * The Icinga module this form belongs to. Usually only set if the
     * form is initialized through the FormLoader
     *
     * @var Module
     */
    protected $icingaModule;

    protected $icingaModuleName;

    private $hintCount = 0;

    public function __construct($options = null)
    {
        $this->callZfConstructor($this->handleOptions($options))
            ->initializePrefixPaths();
    }

    protected function callZfConstructor($options = null)
    {
        parent::__construct($options);
        return $this;
    }

    protected function initializePrefixPaths()
    {
        $this->addPrefixPathsForDirector();
        if ($this->icingaModule && $this->icingaModuleName !== 'director') {
            $this->addPrefixPathsForModule($this->icingaModule);
        }
    }

    protected function addPrefixPathsForDirector()
    {
        $module = Icinga::app()
            ->getModuleManager()
            ->loadModule('director')
            ->getModule('director');

        $this->addPrefixPathsForModule($module);
    }

    public function addPrefixPathsForModule(Module $module)
    {
        $basedir = sprintf(
            '%s/%s/Web/Form',
            $module->getLibDir(),
            ucfirst($module->getName())
        );

        $this->addPrefixPath(
            __NAMESPACE__ . '\\Element\\',
            $basedir . '/Element',
            static::ELEMENT
        );

        return $this;
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
    public function addHtmlHint($html, $options = [])
    {
        return $this->addHtml(
            Html::tag('div', ['class' => 'hint'], $html),
            $options
        );
    }

    public function addHtml($html, $options = [])
    {
        if ($html instanceof ValidHtml) {
            $html = $html->render();
        }

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

    public function optionalEnum($enum, $nullLabel = null)
    {
        if ($nullLabel === null) {
            $nullLabel = $this->translate('- please choose -');
        }

        return array(null => $nullLabel) + $enum;
    }

    protected function handleOptions($options = null)
    {
        if ($options === null) {
            return $options;
        }

        if (array_key_exists('icingaModule', $options)) {
            /** @var Module icingaModule */
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

    protected function loadForm($name, ?Module $module = null)
    {
        if ($module === null) {
            $module = $this->icingaModule;
        }

        return FormLoader::load($name, $module);
    }

    protected function valueIsEmpty($value)
    {
        if ($value === null) {
            return true;
        }

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
