<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierSubstring extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'start', array(
            'label'       => 'Start',
            'required'    => true,
            'description' => sprintf(
                $form->translate(
                    'Please see %s for detailled instructions of how start and end work'
                ),
                'http://php.net/manual/en/function.substr.php'
            )
        ));

        $form->addElement('text', 'length', array(
            'label'    => 'End',
            'description' => sprintf(
                $form->translate(
                    'Please see %s for detailled instructions of how start and end work'
                ),
                'http://php.net/manual/en/function.substr.php'
            )
        ));
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        $length = $this->getSetting('length');
        if (is_numeric($length)) {
            return substr(
                $value,
                (int) $this->getSetting('start'),
                (int) $length
            );
        } else {
            return substr(
                $value,
                (int) $this->getSetting('start')
            );
        }
    }
}
