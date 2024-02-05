<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Protocol\Ldap\LdapUtils;

class PropertyModifierExtractFromDN extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'dn_component', array(
            'label'        => $form->translate('DN component'),
            'description'  => $form->translate('What should we extract from the DN?'),
            'multiOptions' => $form->optionalEnum(array(
                'cn'      => $form->translate('The first (leftmost) CN'),
                'ou'      => $form->translate('The first (leftmost) OU'),
                'first'   => $form->translate('Any first (leftmost) component'),
                'last_ou' => $form->translate('The last (rightmost) OU'),
            )),
            'required'    => true,
        ));

        $form->addElement('select', 'on_failure', array(
            'label'        => $form->translate('On failure'),
            'description'  => $form->translate('What should we do if the desired part does not exist?'),
            'multiOptions' => $form->optionalEnum(array(
                'null' => $form->translate('Set no value (null)'),
                'keep' => $form->translate('Keep the DN as is'),
                'fail' => $form->translate('Let the whole import run fail'),
            )),
            'required'    => true,
        ));
    }

    public function getName()
    {
        return 'Extract from a Distinguished Name (DN)';
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        $parts = LdapUtils::explodeDN($value);
        $result = null;

        switch ($this->getSetting('dn_component')) {
            case 'cn':
                $result = $this->extractFirst($parts, 'cn');
                break;
            case 'ou':
                $result = $this->extractFirst($parts, 'ou');
                break;
            case 'last_ou':
                $result = $this->extractFirst(array_reverse($parts), 'ou');
                break;
            case 'first':
                $result = $this->extractFirst($parts);
                break;
        }

        if ($result === null) {
            switch ($this->getSetting('on_failure')) {
                case 'null':
                    return null;
                case 'keep':
                    return $value;
                case 'fail':
                default:
                    throw new InvalidPropertyException(
                        'DN part extraction failed for %s',
                        var_export($value, true)
                    );
            }
        }

        return $result;
    }

    protected function extractFirst($parts, $what = null)
    {
        foreach ($parts as $part) {
            if (false === ($pos = strpos($part, '='))) {
                continue;
            }

            if (null === $what || strtolower(substr($part, 0, $pos)) === $what) {
                return substr($part, $pos +  1);
            }
        }

        return null;
    }
}
