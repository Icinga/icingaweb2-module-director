<?php

use dipl\Html\Html;
use dipl\Html\HtmlDocument;

/**
 * Please see StoredPassword (the Form Element) for related documentation
 *
 * We're rendering the following fields:
 *
 * - ${name}[_value]:
 * - ${name}[_sent]:
 *
 * Avoid complaints about class names:
 * @codingStandardsIgnoreStart
 */
class Zend_View_Helper_FormStoredPassword extends Zend_View_Helper_FormElement
{
    public function formStoredPassword($name, $value = null, $attribs = null)
    {
        // @codingStandardsIgnoreEnd
        $info = $this->_getInfo($name, $value, $attribs);
        \extract($info); // name, value, attribs, options, listsep, disable
        $sentValue = $this->stripAttribute($attribs, 'sentValue');

        $res = new HtmlDocument();
        $el = Html::tag('input', [
            'type' => 'password',
            'name' => "${name}[_value]",
            'id'   => $id,
        ]);
        $res->add($el);

        $res->add(Html::tag('input', [
            'type'  => 'hidden',
            'name'  => "${name}[_sent]",
            'value' => 'y'
        ]));

        if (\strlen($sentValue)) {
            $el->getAttributes()->set('value', $sentValue);
        } elseif (\strlen($value) > 0) {
            $el->getAttributes()->set('value', '__UNCHANGED_VALUE__');
        }

        return $res;
    }

    protected function stripAttribute(& $attribs, $name, $default = null)
    {
        if (\array_key_exists($name, $attribs)) {
            if (\strlen($attribs[$name])) {
                return $attribs[$name];
            }
            unset($attribs[$name]);
        }

        return $default;
    }
}
