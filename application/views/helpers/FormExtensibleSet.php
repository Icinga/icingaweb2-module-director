<?php

use Icinga\Module\Director\IcingaConfig\ExtensibleSet;

/**
 * View helper for extensible sets
 *
 * Avoid complaints about class names:
 * @codingStandardsIgnoreStart
 */
class Zend_View_Helper_FormExtensibleSet extends Zend_View_Helper_FormElement
{
    private $currentId;

    /**
     * Generates an 'extensible set' element.
     *
     * @codingStandardsIgnoreEnd
     *
     * @param string|array $name If a string, the element name.  If an
     * array, all other parameters are ignored, and the array elements
     * are used in place of added parameters.
     *
     * @param mixed $value The element value.
     *
     * @param array $attribs Attributes for the element tag.
     *
     * @return string The element XHTML.
     */
    public function formExtensibleSet($name, $value = null, $attribs = null)
    {
        $info = $this->_getInfo($name, $value, $attribs);
        extract($info); // name, value, attribs, options, listsep, disable
        if (array_key_exists('multiOptions', $attribs)) {
            $multiOptions = $attribs['multiOptions'];
            unset($attribs['multiOptions']);
            $validOptions = $this->flattenOptions($multiOptions);
        } else {
            $multiOptions = null;
        }

        if (array_key_exists('sorted', $attribs)) {
            $sorted = (bool) $attribs['sorted'];
            unset($attribs['sorted']);
        } else {
            $sorted = false;
        }

        $disableStr = ' disabled="disabled"';

        // build the element
        $disabled = '';
        if ($disable) {
            // disabled
            $disabled = $disableStr;
        }

        $elements = array();
        $v = $this->view;
        $name = $v->escape($name);
        $id = $v->escape($id);

        if ($value instanceof ExtensibleSet) {
            $value = $value->toPlainObject();
        }

        if (is_array($value)) {
            $value = array_filter($value, 'strlen');
        }


        $cnt = 0;
        $total = 0;
        if (is_array($value)) {
            $total = count($value);

            foreach ($value as $val) {

                if ($multiOptions !== null) {
                    if (in_array($val, $validOptions)) {
                        $multiOptions = $this->removeOption($multiOptions, $val);
                    } else {
                        continue; // Value no longer valid
                    }
                }

                $suff = $this->suffix($cnt);

                $htm = '<li><span class="inline-buttons">';

                if ($sorted) {
                    $htm .= $this->renderDownButton($name, $cnt, ($cnt === $total - 1 ? $disableStr : $disabled))
                          . $this->renderUpButton($name, $cnt, ($cnt === 0 ? $disableStr : $disabled));
                }
                $htm .= $this->renderDeleteButton($name, $cnt, $disabled)
                      . '</span>'
                      . '<input type="text"'
                      . ' name="' . $name . '[]"'
                      . ' id="' . $id . $suff . '"'
                      . ' value="' . $v->escape($val) . '"'
                      . $disabled
                      . $this->_htmlAttribs($attribs)
                      . ' />'
                      . '</li>';

                $elements[] = $htm;
                $cnt++;
            }
        }

        $suff = $this->suffix($cnt);
        if ($multiOptions) {
            if (count($multiOptions) > 1 || strlen(key($multiOptions))) {
                $htm = '<li>'
                     . '<span class="inline-buttons">'
                     . $this->renderAddButton($name, $disabled)
                     . '</span>'
                     . $v->formSelect(
                         $name . '[]',
                         null,
                         array(
                             'class'    => 'autosubmit' . ($cnt === 0 ? '' : ' extend-set'),
                             'multiple' => false,
                             'id'       => $id . $suff
                         ),
                         $multiOptions
                     );

                $elements[] = $htm;
            }
        } else {

            $elements[] = '<li><input type="text"'
                    . ' name="' . $name . '[]"'
                    . ($cnt === 0 ? '' : ' class="extend-set"')
                    . ' id="' . $id . $suff . '"'
                    . ' placeholder="' . $v->translate('Add a new one...') . '"'
                    . $disabled
                    . $this->_htmlAttribs($attribs)
                    . $this->getClosingBracket()
                    . '<span class="inline-buttons">'
                    . $this->renderAddButton($name, $disabled)
                    . '</span>'
                    . '</li>';
        }

        return '<ul class="extensible-set'
             . ($sorted ? ' sortable' : '')
             . '">' . "\n  "
             . implode("\n  ", $elements)
             . "</ul>\n";
    }

    private function flattenOptions($options)
    {
        $flat = array();

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                foreach ($option as $k => $o) {
                    $flat[] = $k;
                }
            } else {
                $flat[] = $key;
            }
        }

        return $flat;
    }

    private function removeOption($options, $option)
    {
        $unset = array();
        foreach ($options as $key => & $value) {
            if (is_array($value)) {
                $value = $this->removeOption($value, $option);
                if (empty($value)) {
                    $unset[] = $key;
                }
            } elseif ($key === $option) {
                $unset[] = $key;
            }
        }

        foreach ($unset as $key) {
            unset($options[$key]);
        }

        return $options;
    }

    private function suffix($cnt)
    {
        if ($cnt === 0) {
            return '';
        } else {
            return '_' . $cnt;
        }
    }

    private function renderText($name, $id, $suff, $attribs, $disabled)
    {
        $v = $this->view;

        return '<input type="text"'
            . ' name="' . $name . '[]"'
            . ' id="' . $id . $suff . '"'
            . ' value="' . $v->escape($val) . '"'
            . $disabled
            . $this->_htmlAttribs($attribs)
            . ' />';
    }

    private function renderAddButton($name, $disabled)
    {
        $v = $this->view;

        return '<input type="submit" class="related-action action-add"'
            . ' name="' . $name . '___ADD"'
            . ' value="&#xe805;"'
            . ' title="' . $v->translate('Add a new entry') . '"'
            . $disabled
            . ' />';
    }

    private function renderDeleteButton($name, $cnt, $disabled)
    {
        $v = $this->view;

        return '<input type="submit" class="related-action action-remove"'
            . ' name="' . $name . '_' . $cnt . '__REMOVE' . '"'
            . ' value="&#xe804;"'
            . ' title="' . $v->translate('Remove this entry') . '"'
            . $disabled
            . ' />';
    }

    private function renderUpButton($name, $cnt, $disabled)
    {
        $v = $this->view;

        return '<input type="submit" class="related-action action-move-up"'
            . ' name="' . $name . '_' . $cnt . '__MOVE_UP"'
            . ' value="&#xe825;"'
            . ' title="' . $v->translate('Move up') . '"'
            . $disabled
            . ' />';
    }

    private function renderDownButton($name, $cnt, $disabled)
    {
        $v = $this->view;

        return '<input type="submit" class="related-action action-move-down"'
            . ' name="' . $name . '_' . $cnt . '__MOVE_DOWN"'
            . ' value="&#xe828;"'
            . ' title="' . $v->translate('Move down') . '"'
            . $disabled
            . ' />';
    }
}
