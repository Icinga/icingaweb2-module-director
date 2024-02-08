<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Application\Icinga;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Util;
use LogicException;

class AgentWizard
{
    protected $db;

    protected $host;

    protected $parentZone;

    protected $parentEndpoints;

    /** @var string PKI ticket */
    protected $ticket;

    public function __construct(IcingaHost $host)
    {
        $this->host = $host;
    }

    protected function assertAgent()
    {
        if ($this->host->getResolvedProperty('has_agent') !== 'y') {
            throw new ProgrammingError(
                'The given host "%s" is not an Agent',
                $this->host->getObjectName()
            );
        }
    }

    protected function getCaServer()
    {
        return $this->db()->getDeploymentEndpointName();

        // TODO: This is a problem with Icinga 2. Should look like this:
        // return current($this->getParentEndpoints())->object_name;
    }

    protected function shouldConnectToMaster()
    {
        return $this->host->getResolvedProperty('master_should_connect') !== 'y';
    }

    protected function getParentZone()
    {
        if ($this->parentZone === null) {
            $this->parentZone = $this->loadParentZone();
        }

        return $this->parentZone;
    }

    protected function loadParentZone()
    {
        $db = $this->db();

        if ($zoneId = $this->host->getResolvedProperty('zone_id')) {
            return IcingaZone::loadWithAutoIncId($zoneId, $db);
        } else {
            return IcingaZone::load($db->getMasterZoneName(), $db);
        }
    }

    protected function getParentEndpoints()
    {
        if ($this->parentEndpoints === null) {
            $this->parentEndpoints = $this->loadParentEndpoints();
        }

        return $this->parentEndpoints;
    }

    protected function loadParentEndpoints()
    {
        $db = $this->db()->getDbAdapter();

        $query = $db->select()
            ->from('icinga_endpoint')
            ->where(
                'zone_id = ?',
                $this->getParentZone()->get('id')
            );

        return IcingaEndpoint::loadAll(
            $this->db(),
            $query,
            'object_name'
        );
    }

    /**
     * Get the PKI ticket
     *
     * @return string
     *
     * @throws LogicException If ticket has not been set
     */
    protected function getTicket()
    {
        if ($this->ticket === null) {
            throw new LogicException('Ticket is null');
        }

        return $this->ticket;
    }

    /**
     * Set the PKI ticket
     *
     * @param string $ticket
     *
     * @return $this
     */
    public function setTicket($ticket)
    {
        $this->ticket = $ticket;
    }

    protected function loadPowershellModule()
    {
        return $this->getContribFile('windows-agent-installer/Icinga2Agent.psm1');
    }

    public function renderWindowsInstaller()
    {
        return $this->loadPowershellModule()
            . "\n\n"
            . 'exit Icinga2AgentModule `' . "\n    "
            . $this->renderPowershellParameters([
                'AgentName'       => $this->host->getEndpointName(),
                'Ticket'          => $this->getTicket(),
                'ParentZone'      => $this->getParentZone()->getObjectName(),
                'ParentEndpoints' => array_keys($this->getParentEndpoints()),
                'CAServer'        => $this->getCaServer(),
                'RunInstaller'
            ]);
    }

    public function renderIcinga4WindowsWizardCommand($token)
    {
        $ifwParams = [
            "IfW-DirectorSelfServiceKey" => [
                "Values" => [$token],
            ],
            "IfW-DirectorUrl" => [
                "Values" => [$this->getDirectorUrl()],
            ],
            "IfW-StableRepository" => [
                "Values" => ["https://packages.icinga.com/IcingaForWindows/stable"],
            ]
        ];

        $script = "[Net.ServicePointManager]::SecurityProtocol = 'tls12, tls11';\n"
            . "\$ProgressPreference                         = 'SilentlyContinue';" . "\n"
            . "[string]\$ScriptFile                         = 'C:\Users\Public\IcingaForWindows.ps1';\n"
            . "\n"
            . "Invoke-WebRequest `\n    "
            . $this->renderPowershellParameters([
                'UseBasicParsing',
                'Uri'      => "https://packages.icinga.com/IcingaForWindows/IcingaForWindows.ps1",
                'OutFile' => '$ScriptFile;',
            ]) . "\n"
            . "\n"
            . "& \$ScriptFile `\n    "
            . $this->renderPowershellParameters([
                'ModuleDirectory'  => "C:\Program Files\WindowsPowerShell\Modules\\",
                'InstallCommand'   => json_encode($ifwParams, JSON_UNESCAPED_SLASHES),
                'IcingaRepository' => "https://packages.icinga.com/IcingaForWindows/stable/ifw.repo.json"
            ]);

        return $script;
    }

    public function renderPowershellModuleInstaller($token, $withModule = false)
    {
        if ($withModule) {
            $script = $this->loadPowershellModule() . "\n\n";
        } else {
            $script = '';
        }

        $script .= 'exit Icinga2AgentModule `' . "\n    "
            . $this->renderPowershellParameters([
                'DirectorUrl'       => $this->getDirectorUrl(),
                'DirectorAuthToken' => $token,
                'RunInstaller'
            ]);

        return $script;
    }

    protected function getDirectorUrl()
    {
        $r = Icinga::app()->getRequest();
        $scheme = $r->getServer('HTTP_X_FORWARDED_PROTO', $r->getScheme());

        return sprintf(
            '%s://%s%s/director/',
            $scheme,
            $r->getHttpHost(),
            $r->getBaseUrl()
        );
    }

    protected function renderPowershellParameters($parameters)
    {
        $maxKeyLength = max(array_map('strlen', array_keys($parameters)));
        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                $maxKeyLength = max($maxKeyLength, strlen($value));
            }
        }
        $parts = array();

        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                $parts[] = $this->renderPowershellParameter($value, null, $maxKeyLength);
            } else {
                $parts[] = $this->renderPowershellParameter($key, $value, $maxKeyLength);
            }
        }

        return implode(' `' . "\n    ", $parts);
    }

    protected function renderPowershellParameter($key, $value, $maxKeyLength = null)
    {
        $ret = '-' . $key;
        if ($value === null) {
            return $ret;
        }

        $ret .= ' ';

        if ($maxKeyLength !== null) {
            $ret .= str_repeat(' ', $maxKeyLength - strlen($key));
        }

        if (is_array($value)) {
            $vals = array();
            foreach ($value as $val) {
                $vals[] = $this->renderPowershellString($val);
            }
            $ret .= implode(', ', $vals);
        } elseif (is_int($value)) {
            $ret .= $value;
        } elseif (is_string($value) && $value[0] === '$') {
            $ret .= $value;
        } else {
            $ret .= $this->renderPowershellString($value);
        }

        return $ret;
    }

    protected function renderPowershellString($string)
    {
        // TODO: Escaping
        return "'" . $string . "'";
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = $this->host->getConnection();
        }

        return $this->db;
    }

    public function renderLinuxInstaller()
    {
        $script = $this->loadBashModule();

        $endpoints = [];
        foreach ($this->getParentEndpoints() as $endpoint) {
            $endpoints[$endpoint->getObjectName()] = $endpoint->get('host');
        }

        return $this->replaceBashTemplate($script, [
            'ICINGA2_NODENAME'         => $this->host->getEndpointName(),
            'ICINGA2_CA_TICKET'        => $this->getTicket(),
            'ICINGA2_PARENT_ZONE'      => $this->getParentZone()->getObjectName(),
            'ICINGA2_PARENT_ENDPOINTS' => $endpoints,
            'ICINGA2_CA_NODE'          => $this->getCaServer(),
            'ICINGA2_GLOBAL_ZONES'     => [$this->db()->getDefaultGlobalZoneName()],
        ]);
    }

    protected function loadBashModule()
    {
        return $this->getContribFile('linux-agent-installer/Icinga2Agent.bash');
    }

    protected function replaceBashTemplate($script, $parameters)
    {
        foreach ($parameters as $key => $value) {
            $quotedKey = preg_quote($key, '~');
            if (is_array($value)) {
                $list = [];
                foreach ($value as $k => $v) {
                    if (!is_numeric($k)) {
                        $v = "$k,$v";
                    }
                    $list[] = escapeshellarg($v);
                }
                $value = '(' . join(' ', $list) . ')';
            } else {
                $value = escapeshellarg($value);
            }
            $script = preg_replace("~^#?$quotedKey='@$quotedKey@'$~m", "{$key}={$value}", $script);
        }

        return $script;
    }

    protected function renderBashParameter($key, $value)
    {
        $ret = $key . '=';

        // Cheating, this doesn't really help. We should ship the rendered config
        if (is_array($value) && count($value) === 1) {
            $value = array_shift($value);
        }

        if (is_array($value)) {
            $vals = array();
            foreach ($value as $val) {
                $vals[] = $this->renderPowershellString($val);
            }
            $ret .= '(' . implode(' ', $vals) . ')';
        } else {
            $ret .= $this->renderPowershellString($value);
        }

        return $ret;
    }

    protected function getContribDir()
    {
        return dirname(dirname(dirname(__DIR__))) . '/contrib';
    }

    protected function getContribFile($path)
    {
        return file_get_contents($this->getContribDir() . '/' . $path);
    }
}
