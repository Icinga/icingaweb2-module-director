<?php

use Icinga\Module\Director\Web\Form\Element\FormFieldset;

/**
 * Avoid complaints about class names:
 * @codingStandardsIgnoreStart
 */
class Zend_View_Helper_FormFieldset extends Zend_View_Helper_FormElement
{
    /**
     * Render the fieldset with possible nested fieldsets.
     *
     * @param string $name Form name
     * @param string $content Form content
     * @param array $attribs HTML form attributes
     *
     * @return string
     */
    public function formFieldset($name, $content, $value, $attribs)
    {
//        // @codingStandardsIgnoreEnd
//        if (isset($attribs['content'])) {
//            $content .= $attribs['content'];
//        }

        // Implementation still in progress
        return (new Zend_View_Helper_Fieldset())
            ->fieldset($name, $content, $value, $attribs);
    }
}
