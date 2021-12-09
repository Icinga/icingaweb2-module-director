<?php

namespace Icinga\Module\Director\Web\Table\Dependency;

use Icinga\Web\Url;
use InvalidArgumentException;

/**
 * Minimal HTML helper, as we might be forced to run without ipl
 */
class Html
{
    public static function tag($tag, $attributes = [], $escapedContent = null)
    {
        $result = "<$tag";
        if (! empty($attributes)) {
            foreach ($attributes as $name => $value) {
                if (! preg_match('/^[a-z][a-z0-9:-]*$/i', $name)) {
                    throw new InvalidArgumentException("Invalid attribute name: '$name'");
                }

                $result .= " $name=\"" . self::escapeAttributeValue($value) . '"';
            }
        }

        return "$result>$escapedContent</$tag>";
    }

    public static function webUrl($path, $params)
    {
        return Url::fromPath($path, $params);
    }

    public static function link($escapedLabel, $url, $attributes = [])
    {
        return static::tag('a', [
            'href' => $url,
        ] + $attributes, $escapedLabel);
    }

    public static function linkToGitHub($escapedLabel, $namespace, $repository)
    {
        return static::link(
            $escapedLabel,
            'https://github.com/' . urlencode($namespace) . '/' . urlencode($repository),
            [
                'target' => '_blank',
                'rel'    => 'noreferrer',
                'class'  => 'icon-forward'
            ]
        );
    }

    protected static function escapeAttributeValue($value)
    {
        $value = str_replace('"', '&quot;', $value);
        // Escape ambiguous ampersands
        return preg_replace_callback('/&[0-9A-Z]+;/i', function ($match) {
            $subject = $match[0];

            if (htmlspecialchars_decode($subject, ENT_COMPAT | ENT_HTML5) === $subject) {
                // Ambiguous ampersand
                return str_replace('&', '&amp;', $subject);
            }

            return $subject;
        }, $value);
    }

    public static function escape($any)
    {
        return htmlspecialchars($any);
    }
}
