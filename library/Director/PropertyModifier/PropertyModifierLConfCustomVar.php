<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierLConfCustomVar extends PropertyModifierHook
{
    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        $vars = (object) array();
        $this->extractLConfVars($value, $vars);

        return $vars;
    }

    public function getName()
    {
        return 'Transform LConf CustomVars to Hash';
    }

    public function hasArraySupport()
    {
        return true;
    }

    protected function extractLConfVars($value, $vars)
    {
        if (is_string($value)) {
            $this->extractLConfVar($value, $vars);
        } elseif (is_array($value)) {
            foreach ($value as $val) {
                $this->extractLConfVar($val, $vars);
            }
        }
    }

    protected function extractLConfVar($value, $vars)
    {
        list($key, $val) = preg_split('/ /', $value, 2);
        $key = ltrim($key, '_');
        $vars->$key = $val;
    }
}
