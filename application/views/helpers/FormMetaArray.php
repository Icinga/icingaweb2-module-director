<?php

/**
 * View helper for meta arrays
 *
 * @codingStandardsIgnoreStart
 */
class Zend_View_Helper_FormMetaArray extends Zend_View_Helper_FormElement
{
    public function formMetaArray($name, $values = null, $attribs = null, $options = null, $listsep = null)
    {
        $info = $this->_getInfo($name, $values, $attribs, $options, $listsep);

        extract($info); // name, value, attribs, options, listsep, disable

        $subElement = $attribs['subElement'];
        $subElement->setIsArray(true);
        $elements = [];

        if ($values) {
            foreach($values as $index => $subValue) {
                $subElement->setValue($subValue);
                $elements[] = $subElement->renderViewHelper();
            }
        }

        $subElement->setValue(null);
        $subElement->setAttrib('placeholder', 'Add...');
        $elements[] = $subElement->renderViewHelper();

        return implode('', $elements);
    }
}
