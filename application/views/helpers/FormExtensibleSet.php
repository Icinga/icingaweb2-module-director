<?php

/**
 * View helper for extensible sets
 *
 * Avoid complaints about class names:
 * @codingStandardsIgnoreStart
 */
class Zend_View_Helper_FormExtensibleSet extends Zend_View_Helper_FormElement
{
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
        } else {
            $multiOptions = null;
        }

        if (array_key_exists('sorted', $attribs)) {
            $sorted = (bool) $attribs['sorted'];
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
        $values = array('group a', 'group b');
        $name = $v->escape($name);
        $id = $v->escape($id);

        $cnt = 0;
        $total = 0;
        if (is_array($value)) {
            $total = count($value);

            foreach ($value as $val) {
                if (! strlen($val)) {
                    continue;
                }

                if ($multiOptions !== null) {
                    if (array_key_exists($val, $multiOptions)) {
                        unset($multiOptions[$val]);
                    } else {
                        continue; // Value no longer valid
                    }
                }

                $suff = '_' . $cnt;
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

        $suff = '_' . $cnt;
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
                             'multiple' => false
                         ),
                         $multiOptions
                     );

                $elements[] = $htm;
            }
        } else {

            $elements[] = '<li><input type="text"'
                    . ' name="' . $name . '_add"'
                    . ($cnt === 0 ? '' : ' class="extend-set"')
                    . ' id="' . $id . $suff . '"'
                    . ' placeholder="' . $v->translate('Add a new one...') . '"'
                    . $disabled
                    . $this->_htmlAttribs($attribs)
                    . $this->getClosingBracket()
                    . $this->renderAddButton($name, $disabled)
                    . '</li>';
        }

        return '<ul class="extensible-set">' . "\n  "
             . implode("\n  ", $elements)
             . "</ul>\n";
    }

    public function moveUp($key)
    {
        var_dump("$key up");
    }

    public function moveDown($key)
    {
        var_dump("$key down");
    }

    public function removeKey($key)
    {
        var_dump("$key remove");
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

        return '<input type="submit" class="related-action"'
            . ' name="' . $name . '_ADD"'
            . ' value="&#xe805;"'
            . ' title="' . $v->translate('Remove this entry') . '"'
            . $disabled
            . ' />';
    }

    private function renderDeleteButton($name, $cnt, $disabled)
    {
        $v = $this->view;

        return '<input type="submit" class="related-action"'
            . ' name="' . $name . '_' . $cnt . '__REMOVE' . '"'
            . ' value="&#xe804;"'
            . ' title="' . $v->translate('Remove this entry') . '"'
            . $disabled
            . ' />';
    }

    private function renderUpButton($name, $cnt, $disabled)
    {
        $v = $this->view;

        return '<input type="submit" class="related-action"'
            . ' name="' . $name . '_' . $cnt . '__MOVE_UP"'
            . ' value="&#xe825;"'
            . ' title="' . $v->translate('Move up') . '"'
            . $disabled
            . ' />';
    }

    private function renderDownButton($name, $cnt, $disabled)
    {
        $v = $this->view;

        return '<input type="submit" class="related-action"'
            . ' name="' . $name . '_' . $cnt . '__MOVE_DOWN"'
            . ' value="&#xe828;"'
            . ' title="' . $v->translate('Move down') . '"'
            . $disabled
            . ' />';
    }
}
