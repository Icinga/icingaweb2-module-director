<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CoreBeta;

use Exception;

class ApiClient extends Stream
{
    protected $port;

    public static function create($peer, $port = 5665)
    {
        $stream = new static();
    }

    protected function createClientConnection()
    {
        $context = $this->createSslContext();
        if ($context === false) {
            echo "Unable to set SSL options\n";
            return false;
        }

        $conn = stream_socket_client(
            'ssl://' . $this->peername . ':' . $this->peerport,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );

        return $conn;
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
}
