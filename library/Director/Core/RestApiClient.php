<?php

namespace Icinga\Module\Director\Core;

class RestApiClient
{
    protected $version = 'v1';

    protected $peer;

    protected $port;

    protected $user;

    protected $pass;

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

    public function getPeerIdentity()
    {
        return $this->peer;
    }

    protected function url($url)
    {
        return sprintf('https://%s:%d/%s/%s', $this->peer, $this->port, $this->version, $url);
    }

    protected function request($method, $url, $body = null, $raw = false)
    {
        $auth = base64_encode(sprintf('%s:%s', $this->user, $this->pass));
        $headers = array(
            'Host: ' . $this->getPeerIdentity(),
            'Authorization: Basic ' . $auth,
            'Connection: close'
        );
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
                'header'           => $headers
            ),
            'ssl' => array(
                'verify_peer'   => false,
                // 'cafile'        => $dir . 'cacert.pem',
                // 'verify_depth'  => 5,
                // 'CN_match'      => $peerName // != peer
            )
        );
        $context = stream_context_create($opts);

        if ($raw) {
            return file_get_contents($this->url($url), false, $context);
        } else {
            return RestApiResponse::fromJsonResult(file_get_contents($this->url($url), false, $context));
        }
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
}
