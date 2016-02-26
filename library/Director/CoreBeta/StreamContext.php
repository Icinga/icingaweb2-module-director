<?php

namespace Icinga\Module\Director\CoreBeta;

use Icinga\Exception\ProgrammingError;

class StreamContext
{
    protected $options = array();

    public function ssl()
    {
        if ($this->ssl === null) {
            $this->ssl = new StreamContextSslOptions();
        }

        return $this->ssl;
    }

    public function isSsl()
    {
        return $this->ssl !== null;
    }

    public function setCA(CA $ca)
    {
        // $this->options
    }

    protected function createSslContext()
    {
        $local  = 'ssl://' . $this->local;
        $context = stream_context_create();

        // Hack, we need key and cert:
        $certfile = preg_replace('~\..+$~', '', $this->certname) . '.combi';

        $options = array(
            'ssl' => array(
                'verify_host' => true,
                'cafile'      => $this->ssldir . '/ca.crt',
                'local_cert'  => $this->ssldir . '/' . $certfile,
                'CN_match'    => 'monitor1',
            )
        );

        $result = stream_context_set_option($context, $options);

        return $context;
    }

    public function setContextOptions($options)
    {
        if (array_key_exists('ssl', $options)) {
            throw new ProgrammingError('Direct access to ssl options is not allowed');
        }
    }

    protected function reallySetContextOptions($options)
    {
        if ($this->context === null) {
            $this->options = $options;
        } else {
            stream_context_set_option($this->context, $options);
        }
    }

    protected function lazyContext()
    {
        if ($this->context === null) {
            $this->context = stream_context_create();
            $this->setContextOptions($this->getOptions());

            // stream_context_set_option($this->context
            if ($this->isSsl()) {
                $this->options['ssl'] = $this->ssl()->getOptions();
            }

            $result = stream_context_set_option($this->context, $this->options);
        }

        return $this->context;
    }

    public function getRawContext()
    {
        return $this->lazyContext();
    }
}
