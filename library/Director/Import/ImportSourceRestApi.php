<?php

namespace Icinga\Module\Director\Import;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\RestApi\RestApiClient;
use Icinga\Module\Director\Web\Form\QuickForm;
use InvalidArgumentException;

class ImportSourceRestApi extends ImportSourceHook
{
    public function getName()
    {
        return 'REST API';
    }

    public function fetchData()
    {
        $url = $this->getSetting('url');
        $parts = \parse_url($url);
        if (isset($parts['host'])) {
            $host = $parts['host'];
        } else {
            throw new InvalidArgumentException("URL '$url' has no host");
        }

        $api = new RestApiClient(
            $host,
            $this->getSetting('username'),
            $this->getSetting('password')
        );
        if (isset($parts['path'])) {
            $path = $parts['path'];
        } else {
            $path = '/';
        }
        if (isset($parts['query'])) {
            $url = "$path?" . $parts['query'];
        } else {
            $url = $path;
        }

        $api->setScheme($this->getSetting('scheme'));
        if (isset($parts['port'])) {
            $api->setPort($parts['port']);
        }

        if ($api->getScheme() === 'HTTPS') {
            if ($this->getSetting('ssl_verify_peer', 'y') === 'n') {
                $api->disableSslPeerVerification();
            }
            if ($this->getSetting('ssl_verify_host', 'y') === 'n') {
                $api->disableSslHostVerification();
            }
        }

        if ($proxy = $this->getSetting('proxy')) {
            if ($proxyType = $this->getSetting('proxy_type')) {
                $api->setProxy($proxy, $proxyType);
            } else {
                $api->setProxy($proxy);
            }

            if ($user = $this->getSetting('proxy_user')) {
                $api->setProxyAuth($user, $this->getSetting('proxy_pass'));
            }
        }

        $result = $api->get($url);
        if ($property = $this->getSetting('extract_property')) {
            if (\property_exists($result, $property)) {
                $result = $result->$property;
            } else {
                throw new \RuntimeException(sprintf(
                    'Result has no "%s" property. Available keys: %s',
                    $property,
                    \implode(', ', \array_keys((array) $result))
                ));
            }
        }

        return (array) $result;
    }

    public function listColumns()
    {
        $rows = $this->fetchData();
        $columns = [];

        foreach ($rows as $object) {
            foreach (array_keys((array) $object) as $column) {
                if (! isset($columns[$column])) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        static::addScheme($form);
        static::addSslOptions($form);
        static::addUrl($form);
        static::addResultProperty($form);
        static::addAuthentication($form);
        static::addProxy($form);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addScheme(QuickForm $form)
    {
        $form->addElement('select', 'scheme', [
            'label' => $form->translate('Protocol'),
            'description' => $form->translate(
                'Whether to use encryption when talking to the REST API'
            ),
            'multiOptions' => [
                'HTTPS' => $form->translate('HTTPS (strongly recommended)'),
                'HTTP'  => $form->translate('HTTP (this is plaintext!)'),
            ],
            'class'    => 'autosubmit',
            'value'    => 'HTTPS',
            'required' => true,
        ]);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addSslOptions(QuickForm $form)
    {
        $ssl = ! ($form->getSentOrObjectSetting('scheme', 'HTTPS') === 'HTTP');

        if ($ssl) {
            static::addBoolean($form, 'ssl_verify_peer', array(
                'label'       => $form->translate('Verify Peer'),
                'description' => $form->translate(
                    'Whether we should check that our peer\'s certificate has'
                    . ' been signed by a trusted CA. This is strongly recommended.'
                )
            ), 'y');
            static::addBoolean($form, 'ssl_verify_host', array(
                'label'       => $form->translate('Verify Host'),
                'description' => $form->translate(
                    'Whether we should check that the certificate matches the'
                    . 'configured host'
                )
            ), 'y');
        }
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addUrl(QuickForm $form)
    {
        $form->addElement('text', 'url', array(
            'label'    => 'REST API URL',
            'description' => $form->translate(
                'Something like https://api.example.com/rest/v2/objects'
            ),
            'required' => true,
        ));
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addResultProperty(QuickForm $form)
    {
        $form->addElement('text', 'extract_property', array(
            'label'    => 'Extract property',
            'description' => $form->translate(
                'Often the expected result is provided in a property like "objects".'
                . ' Please specify this if required'
            ),
        ));
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addAuthentication(QuickForm $form)
    {
        $form->addElement('text', 'username', array(
            'label' => $form->translate('Username'),
            'description' => $form->translate(
                'Will be used for SOAP authentication against your vCenter'
            ),
        ));

        $form->addElement('password', 'password', array(
            'label' => $form->translate('Password'),
        ));
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addProxy(QuickForm $form)
    {
        $form->addElement('select', 'proxy_type', [
            'label' => $form->translate('Proxy'),
            'description' => $form->translate(
                'In case your API is only reachable through a proxy, please'
                . ' choose it\'s protocol right here'
            ),
            'multiOptions' => $form->optionalEnum([
                'HTTP'   => $form->translate('HTTP proxy'),
                'SOCKS5' => $form->translate('SOCKS5 proxy'),
            ]),
            'class' => 'autosubmit'
        ]);

        $proxyType = $form->getSentOrObjectSetting('proxy_type');

        if ($proxyType) {
            $form->addElement('text', 'proxy', [
                'label' => $form->translate('Proxy Address'),
                'description' => $form->translate(
                    'Hostname, IP or <host>:<port>'
                ),
                'required' => true,
            ]);
            if ($proxyType === 'HTTP') {
                $form->addElement('text', 'proxy_user', [
                    'label'       => $form->translate('Proxy Username'),
                    'description' => $form->translate(
                        'In case your proxy requires authentication, please'
                        . ' configure this here'
                    ),
                ]);

                $passRequired = strlen($form->getSentOrObjectSetting('proxy_user')) > 0;

                $form->addElement('password', 'proxy_pass', [
                    'label'    => $form->translate('Proxy Password'),
                    'required' => $passRequired
                ]);
            }
        }
    }

    /**
     * @param QuickForm $form
     * @param string $key
     * @param array $options
     * @param string|null $default
     * @throws \Zend_Form_Exception
     */
    protected static function addBoolean(QuickForm $form, $key, $options, $default = null)
    {
        if ($default === null) {
            $form->addElement('OptionalYesNo', $key, $options);
        } else {
            $form->addElement('YesNo', $key, $options);
            $form->getElement($key)->setValue($default);
        }
    }

    /**
     * @param QuickForm $form
     * @param string $key
     * @param string $label
     * @param string $description
     * @throws \Zend_Form_Exception
     */
    protected static function optionalBoolean(QuickForm $form, $key, $label, $description)
    {
        static::addBoolean($form, $key, array(
            'label'       => $label,
            'description' => $description
        ));
    }
}
