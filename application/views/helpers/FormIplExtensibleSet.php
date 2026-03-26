<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

use Icinga\Module\Director\Web\Form\IplElement\ExtensibleSetElement;

/**
 * View helper for extensible sets
 *
 * @codingStandardsIgnoreStart
 */
class Zend_View_Helper_FormIplExtensibleSet extends Zend_View_Helper_FormElement
{
    private $currentId;

    /**
     * @codingStandardsIgnoreEnd

     * @return string The element HTML.
     */
    public function formIplExtensibleSet($name, $value = null, $attribs = null)
    {
        return ExtensibleSetElement::fromZfDingens($name, $value, $attribs);
    }
}
