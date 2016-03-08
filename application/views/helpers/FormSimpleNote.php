<?php

// Avoid complaints about missing namespace and invalid class name
// @codingStandardsIgnoreStart
class Zend_View_Helper_FormSimpleNote extends Zend_View_Helper_FormElement
{
    // @codingStandardsIgnoreEnd

    public function formSimpleNote($name, $value = null)
    {
        $info = $this->_getInfo($name, $value);
        extract($info); // name, value, attribs, options, listsep, disable
        return $value;
    }
}
