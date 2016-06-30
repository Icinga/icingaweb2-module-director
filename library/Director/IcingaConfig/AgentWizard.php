<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Util;

class AgentWizard
{
    protected $db;

    protected $host;

    protected $salt;

    protected $parentZone;

    protected $parentEndpoints;

    public function __construct(IcingaHost $host)
    {
        if ($host->getResolvedProperty('has_agent') !== 'y') {
            throw new ProgrammingError(
                'The given host "%s" is not an Agent',
                $host->object_name
            );
        }

        $this->host = $host;
    }

    protected function getCaServer()
    {
        return $this->db()->getDeploymentEndpointName();

        // TODO: This is a problem. Should look like this:
        return current($this->getParentEndpoints())->object_name;
    }

    protected function shouldConnectToMaster()
    {
        return $this->getResolvedProperty('master_should_connect') !== 'y';
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
                $this->getParentZone()->id
            );

        return IcingaEndpoint::loadAll(
            $this->db(),
            $query,
            'object_name'
        );
    }

    public function setTicketSalt($salt)
    {
        $this->salt = $salt;
        return $this;
    }

    protected function getTicket()
    {
        return Util::getIcingaTicket(
            $this->getCertName(),
            $this->getTicketSalt()
        );
    }

    protected function getTicketSalt()
    {
        if ($this->salt === null) {
            $this->salt = $this->api()->getTicketSalt();
        }

        return $this->salt;
    }

    protected function getCertName()
    {
        return $this->host->object_name;
    }

    protected function loadPowershellModule()
    {
        return file_get_contents(
            dirname(dirname(dirname(__DIR__)))
            . '/contrib/windows-agent-installer/Icinga2Agent.psm1'
        );
    }

    public function renderWindowsInstaller()
    {
        return $this->loadPowershellModule()
            . "\n\n"
            . '$icinga = Icinga2AgentModule `' . "\n    "
            . $this->renderPowershellParameters(
                array(
                    'AgentName'       => $this->getCertName(),
                    'Ticket'          => $this->getTicket(),
                    'ParentZone'      => $this->getParentZone()->object_name,
                    'ParentEndpoints' => array_keys($this->getParentEndpoints()),
                    'CAServer'        => $this->getCaServer(),
                )
            )
            . "\n\n" . '$icinga.installIcinga2Agent()' . "\n";
    }

    protected function renderPowershellParameters($parameters)
    {
        $maxKeyLength = max(array_map('strlen', array_keys($parameters)));
        $parts = array();

        foreach ($parameters as $key => $value) {
            $parts[] = $this->renderPowershellParameter($key, $value, $maxKeyLength);
        }

        return implode(' `' . "\n    ", $parts);
    }

    protected function renderPowershellParameter($key, $value, $maxKeyLength = null)
    {
        $ret = '-' . $key . ' ';

        if ($maxKeyLength !== null) {
            $ret .= str_repeat(' ', $maxKeyLength - strlen($key));
        }

        if (is_array($value)) {
            $vals = array();
            foreach ($value as $val) {
                $vals[] = $this->renderPowershellString($val);
            }
            $ret .= implode(', ', $vals);
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
}
