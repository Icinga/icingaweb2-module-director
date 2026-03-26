<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Form\Element;

use Icinga\Module\Director\PlainObjectRenderer;
use ipl\Html\ValidHtml;

class SimpleNote extends FormElement
{
    public $helper = 'formSimpleNote';

    /**
     * Always ignore this element
     * @codingStandardsIgnoreStart
     *
     * @var boolean
     */
    protected $_ignore = true;
    // @codingStandardsIgnoreEnd

    public function isValid($value, $context = null)
    {
        return true;
    }

    public function setValue($value)
    {
        if (is_object($value) && ! $value instanceof ValidHtml) {
            $value = 'Unexpected object: ' . PlainObjectRenderer::render($value);
        }

        return parent::setValue($value);
    }
}
