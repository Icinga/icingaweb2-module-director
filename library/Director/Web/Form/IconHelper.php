<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Exception\ProgrammingError;

/**
 * Icon helper class
 *
 * Should help to reduce redundant icon-lookup code. Currently with hardcoded
 * icons only, could easily provide support for all of them as follows:
 *
 * $confFile = Icinga::app()
 *     ->getApplicationDir('fonts/fontello-ifont/config.json');
 *
 * $font = json_decode(file_get_contents($confFile));
 * // 'icon-' is to be found in $font->css_prefix_text
 * foreach ($font->glyphs as $icon) {
 * // $icon->css (= 'help') -> 0x . dechex($icon->code)
 * }
 */
class IconHelper
{
    private $icons = array(
        'minus'              => 'e806',
        'trash'              => 'e846',
        'plus'               => 'e805',
        'cancel'             => 'e804',
        'help'               => 'e85b',
        'angle-double-right' => 'e87b',
        'up-big'             => 'e825',
        'down-big'           => 'e828',
        'down-open'          => 'e821',
    );

    private $mappedUtf8Icons;

    private $reversedUtf8Icons;

    private static $instance;

    public function __construct()
    {
        $this->prepareIconMappings();
    }

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function characterIconName($character)
    {
        if (array_key_exists($character, $this->reversedUtf8Icons)) {
            return $this->reversedUtf8Icons[$character];
        } else {
            throw new ProgrammingError('There is no mapping for the given character');
        }
    }

    protected function hexToCharacter($hex)
    {
        return json_decode('"\u' . $hex . '"');
    }

    public function iconCharacter($name)
    {
        if (array_key_exists($name, $this->mappedUtf8Icons)) {
            return $this->mappedUtf8Icons[$name];
        } else {
            return $this->mappedUtf8Icons['help'];
        }
    }

    protected function prepareIconMappings()
    {
        $this->mappedUtf8Icons = array();
        $this->reversedUtf8Icons = array();
        foreach ($this->icons as $name => $hex) {
            $character = $this->hexToCharacter($hex);
            $this->mappedUtf8Icons[$name] = $character;
            $this->reversedUtf8Icons[$character] = $name;
        }
    }
}
