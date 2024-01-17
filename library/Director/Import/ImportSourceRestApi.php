<?php

namespace Icinga\Module\Director\Import;

use Icinga\Exception\InvalidPropertyException;
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
        $result = $this->getRestApi()->get(
            $this->getUrl(),
            null,
            $this->buildHeaders()
        );
        $result = $this->extractProperty($result);

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
     * Extract result from a property specified
     *
     * A simple key, like "objects", will take the final result from key objects
     *
     * If you have a deeper key like "objects" under the key "results", specify this as "results.objects".
     *
     * When a key of the JSON object contains a literal ".", this can be escaped as
     *
     * @param $result
     *
     * @return mixed
     */
    protected function extractProperty($result)
    {
        $property = $this->getSetting('extract_property');
        if (! $property) {
            return $result;
        }

        $parts = preg_split('~(?<!\\\\)\.~', $property);

        // iterate over parts of the attribute path
        $data = $result;
        foreach ($parts as $part) {
            // un-escape any dots
            /** @var string $part */
            $part = preg_replace('~\\\\.~', '.', $part);

            if (property_exists($data, $part)) {
                $data = $data->$part;
            } else {
                throw new \RuntimeException(sprintf(
                    'Result has no "%s" property. Available keys: %s',
                    $part,
                    implode(', ', array_keys((array) $data))
                ));
            }
        }

        return $data;
    }

    protected function buildHeaders()
    {
        $headers = [];

        $text = $this->getSetting('headers', '');
        foreach (preg_split('~\r?\n~', $text, -1, PREG_SPLIT_NO_EMPTY) as $header) {
            $header = trim($header);
            $parts = preg_split('~\s*:\s*~', $header, 2);
            if (count($parts) < 2) {
                throw new InvalidPropertyException('Could not parse header: "%s"', $header);
            }

            $headers[$parts[0]] = $parts[1];
        }

        return $headers;
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
        static::addHeader($form);
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
    protected static function addHeader(QuickForm $form)
    {
        $form->addElement('textarea', 'headers', [
            'label'       => $form->translate('HTTP Header'),
            'description' => implode(' ', [
                $form->translate('Additional headers for the HTTP request.'),
                $form->translate('Specify headers in text format "Header: Value", each header on a new line.'),
            ]),
            'class'       => 'preformatted',
            'rows'        => 4,
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
            static::addBoolean($form, 'ssl_verify_peer', [
                'label'       => $form->translate('Verify Peer'),
                'description' => $form->translate(
                    'Whether we should check that our peer\'s certificate has'
                    . ' been signed by a trusted CA. This is strongly recommended.'
                )
            ], 'y');
            static::addBoolean($form, 'ssl_verify_host', [
                'label'       => $form->translate('Verify Host'),
                'description' => $form->translate(
                    'Whether we should check that the certificate matches the'
                    . 'configured host'
                )
            ], 'y');
        }
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addUrl(QuickForm $form)
    {
        $form->addElement('text', 'url', [
            'label'    => 'REST API URL',
            'description' => $form->translate(
                'Something like https://api.example.com/rest/v2/objects'
            ),
            'required' => true,
        ]);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addResultProperty(QuickForm $form)
    {
        $form->addElement('text', 'extract_property', [
            'label'       => 'Extract property',
            'description' => implode("\n", [
                $form->translate('Often the expected result is provided in a property like "objects".'
                    . ' Please specify this if required.'),
                $form->translate('Also deeper keys can be specific by a dot-notation:'),
                '"result.objects", "key.deeper_key.very_deep"',
                $form->translate('Literal dots in a key name can be written in the escape notation:'),
                '"key\.with\.dots"',
            ])
        ]);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addAuthentication(QuickForm $form)
    {
        $form->addElement('text', 'username', [
            'label' => $form->translate('Username'),
            'description' => $form->translate(
                'Will be used to authenticate against your REST API'
            ),
        ]);

        $form->addElement('storedPassword', 'password', [
            'label' => $form->translate('Password'),
        ]);
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

                $form->addElement('storedPassword', 'proxy_pass', [
                    'label'    => $form->translate('Proxy Password'),
                    'required' => $passRequired
                ]);
            }
        }
    }

    protected function getUrl()
    {
        $url = $this->getSetting('url');
        $parts = \parse_url($url);
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

        return $url;
    }

    protected function getRestApi()
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

        return $api;
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
        static::addBoolean($form, $key, [
            'label'       => $label,
            'description' => $description
        ]);
    }
}
