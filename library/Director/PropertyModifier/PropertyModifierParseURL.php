<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierParseURL extends PropertyModifierHook
{
    /**
     * Array with possible components that can be returned from URL.
     */
    protected static $components = [
        'scheme'   => PHP_URL_SCHEME,
        'host'     => PHP_URL_HOST,
        'port'     => PHP_URL_PORT,
        'path'     => PHP_URL_PATH,
        'query'    => PHP_URL_QUERY,
        'fragment' => PHP_URL_FRAGMENT,
    ];

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'url_component', [
            'label'        => $form->translate('URL component'),
            'description'  => $form->translate('URL component'),
            'multiOptions' => $form->optionalEnum(static::enumComponents()),
            'required'     => true,
        ]);

        $form->addElement('select', 'on_failure', [
            'label'        => $form->translate('On failure'),
            'description'  => $form->translate(
                'What should we do if the URL could not get parsed or component not found?'
            ),
            'multiOptions' => $form->optionalEnum([
                'null' => $form->translate('Set no value (null)'),
                'keep' => $form->translate('Keep the property as is'),
                'fail' => $form->translate('Let the whole import run fail'),
            ]),
            'required' => true,
        ]);
    }

    protected static function enumComponents()
    {
        $components = array_keys(self::$components);
        return array_combine($components, $components);
    }

    public function getName()
    {
        return 'Parse a URL and return its components';
    }

    public function transform($value)
    {
        $component = self::$components[$this->getSetting('url_component')];
        $response = parse_url($value, $component);

        // if component not found $response will be null, false if seriously malformed URL
        if ($response === null || $response === false) {
            switch ($this->getSetting('on_failure')) {
                case 'null':
                    return null;
                case 'keep':
                    return $value;
                case 'fail':
                default:
                    throw new InvalidPropertyException(
                        'Parsing URL "%s" failed.',
                        $value
                    );
            }
        }

        return $response;
    }
}
