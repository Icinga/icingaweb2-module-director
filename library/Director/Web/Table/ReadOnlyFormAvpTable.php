<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\PlainObjectRenderer;
use Icinga\Module\Director\Web\Form\QuickForm;
use Zend_Form_Element as ZfElement;
use Zend_Form_DisplayGroup as ZfDisplayGroup;

class ReadOnlyFormAvpTable
{
    protected $form;

    public function __construct(QuickForm $form)
    {
        $this->form = $form;
    }

    protected function renderDisplayGroups(QuickForm $form)
    {
        $html = '';

        foreach ($form->getDisplayGroups() as $group) {
            $elements = $this->filterGroupElements($group);

            if (empty($elements)) {
                continue;
            }

            $html .= '<tr><th colspan="2" style="text-align: right">' . $group->getLegend() . '</th></tr>';
            $html .= $this->renderElements($elements);
        }

        return $html;
    }

    /**
     * @param ZfDisplayGroup $group
     * @return ZfElement[]
     */
    protected function filterGroupElements(ZfDisplayGroup $group)
    {
        $blacklist = array('disabled', 'assign_filter');
        $elements = array();
        /** @var ZfElement $element */
        foreach ($group->getElements() as $element) {
            if ($element->getValue() === null) {
                continue;
            }

            if ($element->getType() === 'Zend_Form_Element_Hidden') {
                continue;
            }

            if (in_array($element->getName(), $blacklist)) {
                continue;
            }


            $elements[] = $element;
        }

        return $elements;
    }

    protected function renderElements($elements)
    {
        $html = '';
        foreach ($elements as $element) {
            $html .= $this->renderElement($element);
        }

        return $html;
    }

    /**
     * @param ZfElement $element
     *
     * @return string
     */
    protected function renderElement(ZfElement $element)
    {
        $value = $element->getValue();
        return '<tr><th>'
            . $this->escape($element->getLabel())
            . '</th><td>'
            . $this->renderValue($value)
            . '</td></tr>';
    }

    protected function renderValue($value)
    {
        if (is_string($value)) {
            return $this->escape($value);
        } elseif (is_array($value)) {
            return $this->escape(implode(', ', $value));
        }
        return $this->escape(PlainObjectRenderer::render($value));
    }

    protected function escape($string)
    {
        return htmlspecialchars($string);
    }

    public function render()
    {
        $this->form->initializeForObject();
        return '<table class="name-value-table">' . "\n"
            . $this->renderDisplayGroups($this->form)
            . '</table>';
    }
}
