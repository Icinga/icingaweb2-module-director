<?php

namespace Icinga\Module\Director;

use Icinga\Authentication\Auth;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Exception\NotImplementedError;
use Icinga\Exception\ProgrammingError;
use dipl\Html\Html;
use dipl\Html\Link;
use RuntimeException;
use Zend_Db_Expr;

class Util
{
    protected static $auth;

    protected static $allowedResources;

    public static function pgBinEscape($binary)
    {
        if ($binary instanceof Zend_Db_Expr) {
            throw new RuntimeException('Trying to escape binary twice');
        }

        return new Zend_Db_Expr("'\\x" . bin2hex($binary) . "'");
    }

    /**
     * PBKDF2 - Password-Based Cryptography Specification (RFC2898)
     *
     * This method strictly follows examples in php.net's documentation
     * comments. Hint: RFC6070 would be a good source for related tests
     *
     * @param string $alg        Desired hash algorythm (sha1, sha256...)
     * @param string $secret     Shared secret, password
     * @param string $salt       Hash salt
     * @param int    $iterations How many iterations to perform. Please use at
     *                           least 1000+. More iterations make it slower
     *                           but more secure.
     * @param int    $length     Desired key length
     * @param bool   $raw        Returns the binary key if true, hex string otherwise
     *
     * @throws NotImplementedError when asking for an unsupported algorightm
     * @throws ProgrammingError    when passing invalid parameters
     *
     * @return string  A $length byte long key, derived from secret and salt
     */
    public static function pbkdf2($alg, $secret, $salt, $iterations, $length, $raw = false)
    {
        if (! in_array($alg, hash_algos(), true)) {
            throw new NotImplementedError('No such hash algorithm found: "%s"', $alg);
        }

        if ($iterations <= 0 || $length <= 0) {
            throw new ProgrammingError('Positive iterations and length required');
        }

        $hashLength = strlen(hash($alg, '', true));
        $blocks = ceil($length / $hashLength);

        $out = '';

        for ($i = 1; $i <= $blocks; $i++) {
            // $i encoded as 4 bytes, big endian.
            $last = $salt . pack('N', $i);
            // first iteration
            $last = $xorsum = hash_hmac($alg, $last, $secret, true);
            // perform the other $iterations - 1 iterations
            for ($j = 1; $j < $iterations; $j++) {
                $xorsum ^= ($last = hash_hmac($alg, $last, $secret, true));
            }
            $out .= $xorsum;
        }

        if ($raw) {
            return substr($out, 0, $length);
        }

        return bin2hex(substr($out, 0, $length));
    }

    public static function getIcingaTicket($certname, $salt)
    {
        return self::pbkdf2('sha1', $certname, $salt, 50000, 20);
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
            if ($resource->get('type') === $type && self::resourceIsAllowed($name)) {
                $resources[$name] = $name;
            }
        }

        return $resources;
    }

    public static function addDbResourceFormElement(QuickForm $form, $name)
    {
        static::addResourceFormElement($form, $name, 'db');
    }

    public static function addLdapResourceFormElement(QuickForm $form, $name)
    {
        static::addResourceFormElement($form, $name, 'ldap');
    }

    protected static function addResourceFormElement(QuickForm $form, $name, $type)
    {
        $list = self::enumResources($type);

        $form->addElement('select', $name, array(
            'label'        => 'Resource name',
            'multiOptions' => $form->optionalEnum($list),
            'required'     => true,
        ));

        if (empty($list)) {
            if (self::hasPermission('config/application/resources')) {
                $form->addHtmlHint(Html::sprintf(
                    $form->translate('Please click %s to create new resources'),
                    Link::create(
                        $form->translate('here'),
                        'config/resource',
                        null,
                        ['data-base-target' => '_main']
                    )
                ));
                $msg = sprintf($form->translate('No %s resource available'), $type);
            } else {
                $msg = $form->translate('Please ask an administrator to grant you access to resources');
            }

            $form->getElement($name)->addError($msg);
        }
    }
}
