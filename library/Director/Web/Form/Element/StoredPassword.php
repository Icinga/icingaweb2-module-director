<?php

namespace Icinga\Module\Director\Web\Form\Element;

use Zend_Form_Element_Text as ZfText;

/**
 * StoredPassword
 *
 * This is a special form field and it might look a little bit weird at first
 * sight. It's main use-case are stored cleartext passwords a user should be
 * allowed to change.
 *
 * While this might sound simple, it's quite tricky if you try to fulfill the
 * following requirements:
 *
 * - the current password should not be rendered to the HTML page (unless the
 *   user decides to change it)
 * - it must be possible to visually distinct whether a password has been set
 * - it should be impossible to "see" the length of the stored password
 * - a changed password must be persisted
 * - forms might be subject to multiple submissions in case other fields fail.
 *   If the user changed the password during the first submission attempt, the
 *   new string should not be lost.
 * - all this must happen within the bounds of ZF1 form elements and related
 *   view helpers. This means that there is no related context available - and
 *   we do not know whether the form has been submitted and whether the current
 *   values have been populated from DB
 *
 * @package Icinga\Module\Director\Web\Form\Element
 */
class StoredPassword extends ZfText
{
    const UNCHANGED = '__UNCHANGED_VALUE__';

    public $helper = 'formStoredPassword';

    public function setValue($value)
    {
        if (\is_array($value) && isset($value['_value'], $value['_sent'])
            && $value['_sent'] === 'y'
        ) {
            $value = $sentValue = $value['_value'];
            if ($sentValue !== self::UNCHANGED) {
                $this->setAttrib('sentValue', $sentValue);
            }
        } else {
            $sentValue = null;
        }

        if ($value === self::UNCHANGED) {
            return $this;
        } else {
            // Workaround for issue with modified DataTypes. This is Director-specific
            if (\is_array($value)) {
                $value = \json_encode($value);
            }

            return parent::setValue((string) $value);
        }
    }
}
