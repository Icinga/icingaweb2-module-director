<?php

# get ipv4 adress by hostname lookup
# return string to support proper synchronization

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierGetIPv4ByName extends PropertyModifierHook
{
    protected static $types = array(
        'A'     => DNS_A,
    );

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'record_type', array(
            'label'        => 'Record type',
            'description'  => $form->translate('DNS record type'),
            'multiOptions' => $form->optionalEnum(static::enumTypes()),
            'required'    => true,
        ));

        $form->addElement('select', 'on_failure', array(
            'label'        => 'On failure',
            'description'  => $form->translate('What should we do if the DNS lookup fails?'),
            'multiOptions' => $form->optionalEnum(array(
                'null' => $form->translate('Set no value (null)'),
                'keep' => $form->translate('Keep the property as is'),
                'fail' => $form->translate('Let the whole import run fail'),
            )),
            'required'    => true,
        ));
    }

    protected static function enumTypes()
    {
        $types = array_keys(self::$types);
        return array_combine($types, $types);
    }

    public function getName()
    {
        return 'Get IPv4 address from hostname';
    }

    public function transform($value)
    {
        $type = self::$types[$this->getSetting('record_type')];
        $response = dns_get_record($value, $type);

        if ($response === false) {
            switch ($this->getSetting('on_failure')) {
                case 'null':
                    return null;
                case 'keep':
                    return $value;
                case 'fail':
                default:
                    throw new InvalidPropertyException(
                        'DNS lookup failed for "%s"',
                        $value
                    );
            }
        }

        $result = array();
        switch ($type) {
            case DNS_A:
                return $this->extractProperty('ip', $response);
                return $response;
        }

        return $result;
    }

    protected function extractProperty($key, $response)
    {
        $result = array();
        foreach ($response as $entry) {
            $result[] = $entry[$key];
        }

        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            sort($result, SORT_NATURAL);
        } else {
            natsort($result);
            $result = array_values($result);
        }

        return  array_shift($result);
    }
}
