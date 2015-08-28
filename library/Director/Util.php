<?php

namespace Icinga\Module\Director;

use Icinga\Authentication\Auth;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Web\Url;
use Zend_Db_Expr;

class Util
{
    protected static $auth;

    protected static $allowedResources;

    public static function pgBinEscape($binary)
    {
        return new Zend_Db_Expr("'\\x" . bin2hex($binary) . "'");
    }

    public static function hex2binary($bin)
    {
        return pack('H*', $bin);
    }

    public static function binary2hex($hex)
    {
        return current(unpack('H*', $hex));
    }

    public static function auth()
    {
        if (self::$auth === null) {
            self::$auth = Auth::getInstance();
        }
        return self::$auth;
    }

    public static function hasPermission($name)
    {
        return self::auth()->hasPermission($name);
    }

    public static function getRestrictions($name)
    {
        return self::auth()->getRestrictions($name);
    }

    public static function resourceIsAllowed($name)
    {
        if (self::$allowedResources === null) {
            $restrictions = self::getRestrictions('director/resources/use');
            $list = array();
            foreach ($restrictions as $restriction) {
                foreach (preg_split('/\s*,\s*/', $restriction, -1, PREG_SPLIT_NO_EMPTY) as $key) {
                    $list[$key] = $key;
                }
            }

            self::$allowedResources = $list;
        } else {
            $list = self::$allowedResources;
        }

        if (empty($list) || array_key_exists($name, $list)) {
            return true;
        }

        return false;
    }

    public static function enumDbResources()
    {
        return self::enumResources('db');
    }

    public static function enumLdapResources()
    {
        return self::enumResources('ldap');
    }

    protected static function enumResources($type)
    {
        $resources = array();
        foreach (ResourceFactory::getResourceConfigs() as $name => $resource) {
            if ($resource->type === $type && self::resourceIsAllowed($name)) {
                $resources[$name] = $name;
            }
        }

        return $resources;
    }

    public static function addDbResourceFormElement(QuickForm $form, $name)
    {
        return self::addResourceFormElement($form, $name, 'db');
    }

    public static function addLdapResourceFormElement(QuickForm $form, $name)
    {
        return self::addResourceFormElement($form, $name, 'ldap');
    }

    protected static function addResourceFormElement(QuickForm $form, $name, $type)
    {
        $list = self::enumResources($type);

        $form->addElement('select', $name, array(
            'label'        => 'Resource name',
            'multiOptions' => $form->optionalEnum($list),
            'required'     => true,
        ));

        if (true && empty($list)) {
            if (self::hasPermission('config/application/resources')) {
                $hint = $form->translate('Please click %s to create new resources');
                $link = sprintf(
                    '<a href="' . Url::fromPath('config/resource') . '" data-base-target="_main">%s</a>',
                    $form->translate('here')
                );
                $form->addHtmlHint(sprintf($hint, $link));
                $msg = $form->translate('No db resource available');
            } else {
                $msg = $form->translate('Please ask an administrator to grant you access to resources');
            }

            $form->getElement($name)->addError($msg);
        }
    }
}
