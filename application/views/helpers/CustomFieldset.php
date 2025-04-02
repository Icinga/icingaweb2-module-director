<?php

/**
 * Avoid complaints about class names:
 * @codingStandardsIgnoreStart
 */
class Zend_View_Helper_CustomFieldset extends Zend_View_Helper_Fieldset
{
    /**
     * Render the fieldset with possible nested fieldsets.
     *
     * @access public
     *
     * @param string $name Form name
     * @param string $content Form content
     * @param array $attribs HTML form attributes
     *
     * @return string
     */
    public function customFieldset($name, $content, $attribs = null)
    {
//        // @codingStandardsIgnoreEnd
//        if (isset($attribs['content'])) {
//            $content .= $attribs['content'];
//        }

        $info    = $this->_getInfo($name, $content, $attribs);
        extract($info); // name, id, value, attribs, options, listsep, disable, escape

        // Implementation still in progress
        var_dump($name);die;
        return (new Zend_View_Helper_Fieldset())
            ->fieldset($name, $content, $attribs);
    }
}
