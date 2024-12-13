<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierFromAdSid extends PropertyModifierHook
{
    public function getName()
    {
        return 'Decode a binary object SID (MSAD)';
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        // Strongly inspired by
        // http://www.chadsikorra.com/blog/decoding-and-encoding-active-directory-objectsid-php
        //
        // Not perfect yet, but should suffice for now. When improving this please also see:
        // https://blogs.msdn.microsoft.com/oldnewthing/20040315-00/?p=40253

        $sid = $value;
        $sidHex = unpack('H*hex', $value);
        $sidHex = $sidHex['hex'];
        $subAuths = implode('-', unpack('H2/H2/n/N/V*', $sid));

        $revLevel = hexdec(substr($sidHex, 0, 2));
        $authIdent = hexdec(substr($sidHex, 4, 12));

        return sprintf('S-%s-%s-%s', $revLevel, $authIdent, $subAuths);
    }
}
