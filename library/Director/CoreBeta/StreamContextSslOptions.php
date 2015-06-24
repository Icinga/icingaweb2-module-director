<?php

namespace Icinga\Module\Director\CoreBeta;

use Icinga\Exception\ProgrammingError;

class StreamContextSslOptions
{
    protected $options = array(
        'verify_peer' => true,
    );

    public function setCA(CA $ca)
    {
        $this->ca = $ca;
    }

    public function capturePeerCert($capture = true)
    {
        $this->options['capture_peer_cert'] = (bool) $capture;
        return $this;
    }

    public function capturePeerChain($capture = true)
    {
        $this->options['capture_peer_chain'] = (bool) $capture;
        return $this;
    }

    public function setCiphers($ciphers)
    {
        $this->options['ciphers'] = $ciphers;
        return $this;
    }

    public function setPeerName($name)
    {
        if (version_compare(PHP_VERSION, '5.6.0') >= 0) {
            $this->options['peer_name'] = $name;
            $this->options['verify_peer_name'] = true;
        } else {
            $this->options['CN_match'] = $name;
        }
        return $this;
    }

    public function getOptions()
    {
        // TODO: Fail on missing cert
        return $this->options;
    }
}
