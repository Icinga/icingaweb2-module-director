<?php

namespace Icinga\Module\Director\RestApi;

use Icinga\Module\Director\Core\Json;
use InvalidArgumentException;
use RuntimeException;

class RestApiClient
{
    private $curl;

    /** @var string HTTP or HTTPS */
    private $scheme;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $user;

    /** @var string */
    private $pass;

    /** @var bool */
    private $verifySslPeer = true;

    /** @var bool */
    private $verifySslHost = true;

    /** @var string */
    private $proxy;

    /** @var string */
    private $proxyType;

    /** @var string */
    private $proxyUser;

    /** @var string */
    private $proxyPass;

    /** @var array */
    private $proxyTypes = [
        'HTTP'   => CURLPROXY_HTTP,
        'SOCKS5' => CURLPROXY_SOCKS5,
    ];

    /**
     * RestApiClient constructor.
     *
     * Please note that only the host is required, user and pass are optional
     *
     * @param string $host
     * @param string|null $user
     * @param string|null $pass
     */
    public function __construct($host, $user = null, $pass = null)
    {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
     * Use a proxy
     *
     * @param $url
     * @param string $type Either HTTP or SOCKS5
     * @return $this
     */
    public function setProxy($url, $type = 'HTTP')
    {
        $this->proxy = $url;
        if (\is_int($type)) {
            $this->proxyType = $type;
        } else {
            $this->proxyType = $this->proxyTypes[$type];
        }
        return $this;
    }

    /**
     * @param string $user
     * @param string $pass
     * @return $this
     */
    public function setProxyAuth($user, $pass)
    {
        $this->proxyUser = $user;
        $this->proxyPass = $pass;
        return $this;
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        if ($this->scheme === null) {
            return 'HTTPS';
        } else {
            return $this->scheme;
        }
    }

    public function setScheme($scheme)
    {
        $scheme = \strtoupper($scheme);
        if (! \in_array($scheme, ['HTTP', 'HTTPS'])) {
            throw new InvalidArgumentException("Got invalid scheme: $scheme");
        }

        $this->scheme = $scheme;
        return $this;
    }

    /**
     * @return string
     */
    public function getPort()
    {
        if ($this->port === null) {
            return $this->getScheme() === 'HTTPS' ? 443 : 80;
        } else {
            return $this->port;
        }
    }

    /**
     * @param int|string|null $port
     * @return $this
     */
    public function setPort($port)
    {
        if ($port === null) {
            $this->port = null;
            return $this;
        }
        $port = (int) ($port);
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException("Got invalid port: $port");
        }

        $this->port = $port;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDefaultPort()
    {
        return $this->port === null
            || $this->getScheme() === 'HTTPS' && $this->getPort() === 443
            || $this->getScheme() === 'HTTP' && $this->getPort() === 80;
    }

    /**
     * @param bool $disable
     * @return $this
     */
    public function disableSslPeerVerification($disable = true)
    {
        $this->verifySslPeer = ! $disable;
        return $this;
    }

    /**
     * @param bool $disable
     * @return $this
     */
    public function disableSslHostVerification($disable = true)
    {
        $this->verifySslHost = ! $disable;
        return $this;
    }

    /**
     * @param string $url
     * @return string
     */
    public function url($url)
    {
        return \sprintf(
            '%s://%s%s/%s',
            \strtolower($this->getScheme()),
            $this->host,
            $this->isDefaultPort() ? '' : ':' . $this->getPort(),
            ltrim($url, '/')
        );
    }

    /**
     * @param string $url
     * @param mixed $body
     * @param array $headers
     * @return mixed
     */
    public function get($url, $body = null, $headers = [])
    {
        return $this->request('get', $url, $body, $headers);
    }

    /**
     * @param $url
     * @param null $body
     * @param array $headers
     * @return mixed
     */
    public function post($url, $body = null, $headers = [])
    {
        return $this->request('post', $url, Json::encode($body), $headers);
    }

    /**
     * @param $method
     * @param $url
     * @param null $body
     * @param array $headers
     * @return mixed
     */
    protected function request($method, $url, $body = null, $headers = [])
    {
        $sendHeaders = ['Host: ' . $this->host];
        foreach ($headers as $key => $val) {
            $sendHeaders[] = "$key: $val";
        }

        if (! \in_array('Accept', $headers)) {
            $sendHeaders[] = 'Accept: application/json';
        }

        $url = $this->url($url);
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $sendHeaders,
            CURLOPT_CUSTOMREQUEST  => \strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];

        if ($this->getScheme() === 'HTTPS') {
            $opts[CURLOPT_SSL_VERIFYPEER] = $this->verifySslPeer;
            $opts[CURLOPT_SSL_VERIFYHOST] = $this->verifySslHost ? 2 : 0;
        }

        if ($this->user !== null) {
            $opts[CURLOPT_USERPWD] = \sprintf('%s:%s', $this->user, $this->pass);
        }

        if ($this->proxy) {
            $opts[CURLOPT_PROXY] = $this->proxy;
            $opts[CURLOPT_PROXYTYPE] = $this->proxyType;

            if ($this->proxyUser) {
                $opts['CURLOPT_PROXYUSERPWD'] = \sprintf(
                    '%s:%s',
                    $this->proxyUser,
                    $this->proxyPass
                );
            }
        }

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        $curl = $this->curl();
        \curl_setopt_array($curl, $opts);

        $res = \curl_exec($curl);
        if ($res === false) {
            throw new RuntimeException('CURL ERROR: ' . \curl_error($curl));
        }

        $statusCode = \curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($statusCode === 401) {
            throw new RuntimeException(
                'Unable to authenticate, please check your REST API credentials'
            );
        }

        if ($statusCode >= 400) {
            throw new RuntimeException(
                "Got $statusCode: " . \var_export($res, true)
            );
        }

        return Json::decode($res);
    }

    /**
     * @throws RuntimeException
     */
    protected function curl()
    {
        if ($this->curl === null) {
            $this->curl = curl_init(sprintf('https://%s:%d', $this->host, $this->port));
            if (! $this->curl) {
                throw new RuntimeException('CURL INIT FAILED');
            }
        }

        return $this->curl;
    }
}
