<?php

namespace Icinga\Module\Director\Core;

use Icinga\Application\Benchmark;
use Icinga\Exception\ConfigurationError;
use Exception;

class RestApiClient
{
    protected $version = 'v1';

    protected $peer;

    protected $port;

    protected $user;

    protected $pass;

    protected $curl;

    protected $readBuffer = '';

    protected $onEvent;

    protected $onEventWantsRaw;

    public function __construct($peer, $port = 5665, $cn = null)
    {
        $this->peer = $peer;
        $this->port = $port;
    }

    // TODO: replace with Web2 CA trust resource plus cert and get rid
    //       of user/pass or at least strongly advise against using it
    public function setCredentials($user, $pass)
    {
        $this->user = $user;
        $this->pass = $pass;

        return $this;
    }

    public function onEvent($callback, $raw = false)
    {
        $this->onEventWantsRaw = $raw;
        $this->onEvent = $callback;
        return $this;
    }

    public function getPeerIdentity()
    {
        return $this->peer;
    }

    protected function url($url)
    {
        return sprintf('https://%s:%d/%s/%s', $this->peer, $this->port, $this->version, $url);
    }

    // protected function request($method, $url, $body = null, $raw = false)
    public function request($method, $url, $body = null, $raw = false, $stream = false)
    {
        if (function_exists('curl_version')) {
            return $this->curlRequest($method, $url, $body, $raw, $stream);
        /*
        // Completely disabled fallback method, caused too many issues
        // with hanging connections on specific PHP versions
        } elseif (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            // TODO: fail if stream
            return $this->phpRequest($method, $url, $body, $raw);
        */
        } else {
            throw new Exception(
                'No CURL extension detected, it must be installed and enabled'
            );
        }
    }

    protected function phpRequest($method, $url, $body = null, $raw = false)
    {
        $auth = base64_encode(sprintf('%s:%s', $this->user, $this->pass));
        $headers = array(
            'Host: ' . $this->getPeerIdentity(),
            'Authorization: Basic ' . $auth,
            'Connection: close'
        );

        if (! $raw) {
            $headers[] = 'Accept: application/json';
        }

        if ($body !== null) {
            $body = json_encode($body);
            $headers[] = 'Content-Type: application/json';
        }

        $opts = array(
            'http' => array(
                'protocol_version' => '1.1',
                'user_agent'       => 'Icinga Web 2.0 - Director',
                'method'           => strtoupper($method),
                'content'          => $body,
                'header'           => $headers,
                'ignore_errors' => true
            ),
            'ssl' => array(
                // TODO: Fix this!
                'verify_peer'   => false,
                // 'cafile'        => $dir . 'cacert.pem',
                // 'verify_depth'  => 5,
                // 'CN_match'      => $peerName // != peer
            )
        );
        $context = stream_context_create($opts);

        Benchmark::measure('Rest Api, sending ' . $url);
        $res = file_get_contents($this->url($url), false, $context);
        if (substr(array_shift($http_response_header), 0, 10) !== 'HTTP/1.1 2') {
            throw new Exception($res);
        }
        Benchmark::measure('Rest Api, got response');
        if ($raw) {
            return $res;
        } else {
            return RestApiResponse::fromJsonResult($res);
        }
    }

    protected function curlRequest($method, $url, $body = null, $raw = false, $stream = false)
    {
        $auth = sprintf('%s:%s', $this->user, $this->pass);
        $headers = array(
            'Host: ' . $this->getPeerIdentity(),
            // 'Connection: close'
        );

        if (! $raw) {
            $headers[] = 'Accept: application/json';
        }

        if ($body !== null) {
            $body = json_encode($body);
            $headers[] = 'Content-Type: application/json';
        }

        $curl = $this->curl();
        $opts = array(
            CURLOPT_URL            => $this->url($url),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERPWD        => $auth,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,

            // TODO: Fix this!
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        if ($stream) {
            $opts[CURLOPT_WRITEFUNCTION] = array($this, 'readPart');
            $opts[CURLOPT_TCP_NODELAY] = 1;
        }

        curl_setopt_array($curl, $opts);
        // TODO: request headers, validate status code

        Benchmark::measure('Rest Api, sending ' . $url);
        $res = curl_exec($curl);
        if ($res === false) {
            throw new Exception('CURL ERROR: ' . curl_error($curl));
        }

        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($statusCode === 401) {
            throw new ConfigurationError(
                'Unable to authenticate, please check your API credentials'
            );
        }

        Benchmark::measure('Rest Api, got response');

        if ($stream) {
            return $this;
        }

        if ($raw) {
            return $res;
        } else {
            return RestApiResponse::fromJsonResult($res);
        }
    }

    /**
     * @param  resource $curl
     * @param  $data
     * @return int
     */
    protected function readPart($curl, & $data)
    {
        $length = strlen($data);
        $this->readBuffer .= $data;
        $this->processEvents();
        return $length;
    }

    public function get($url, $body = null)
    {
        return $this->request('get', $url, $body);
    }

    public function getRaw($url, $body = null)
    {
        return $this->request('get', $url, $body, true);
    }

    public function post($url, $body = null)
    {
        return $this->request('post', $url, $body);
    }

    public function put($url, $body = null)
    {
        return $this->request('put', $url, $body);
    }

    public function delete($url, $body = null)
    {
        return $this->request('delete', $url, $body);
    }

    /**
     * @throws Exception
     *
     * @return resource
     */
    protected function curl()
    {
        if ($this->curl === null) {
            $this->curl = curl_init(sprintf('https://%s:%d', $this->peer, $this->port));
            if (! $this->curl) {
                throw new Exception('CURL INIT ERROR: ' . curl_error($this->curl));
            }
        }

        return $this->curl;
    }

    protected function processEvents()
    {
        $offset = 0;
        while (false !== ($pos = strpos($this->readBuffer, "\n", $offset))) {
            if ($pos === $offset) {
                // echo "Got empty line $offset / $pos\n";
                $offset = $pos + 1;
                continue;
            }
            $this->processReadBuffer($offset, $pos);

            $offset = $pos + 1;
        }

        if ($offset > 0) {
            $this->readBuffer = substr($this->readBuffer, $offset + 1);
        }

        // echo "REMAINING: " . strlen($this->readBuffer) . "\n";
    }

    protected function processReadBuffer($offset, $pos)
    {
        if ($this->onEvent === null) {
            return;
        }

        $func = $this->onEvent;
        $str = substr($this->readBuffer, $offset, $pos);
        // printf("Processing %s bytes\n", strlen($str));

        if ($this->onEventWantsRaw) {
            $func($str);
        } else {
            $decoded = json_decode($str);
            if ($decoded === false) {
                throw new Exception('Got invalid JSON: ' . $str);
            }

            $func($decoded);
        }
    }

    public function __destruct()
    {
        if ($this->curl !== null && is_resource($this->curl)) {
            curl_close($this->curl);
        }
    }
}
