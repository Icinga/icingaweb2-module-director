<?php

namespace Icinga\Module\Director;

class Settings
{
    protected $connection;

    protected $db;

    protected $cache;

    protected $defaults = array(
        'default_global_zone'             => 'director-global',
        'magic_apply_for'                 => '_director_apply_for',
        'config_format'                   => 'v2',
        'override_services_varname'       => '_override_servicevars',
        'override_services_templatename'  => 'host var overrides (Director)',
        'disable_all_jobs'                => 'n', // 'y'
        'enable_audit_log'                => 'n',
        'deployment_mode_v1'              => 'active-passive',
        'deployment_path_v1'              => null,
        'activation_script_v1'            => null,
        'self-service/agent_name'         => 'fqdn',
        'self-service/transform_hostname' => '0'
        // 'experimental_features'       => null, // 'allow'
        // 'master_zone'                 => null,
    );

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function get($key, $default = null)
    {
        if (null === ($value = $this->getStoredValue($key, $default))) {
            return $this->getDefaultValue($key);
        } else {
            return $value;
        }
    }

    public function getStoredValue($key, $default = null)
    {
        if (null === ($value = $this->getSetting($key))) {
            return $default;
        } else {
            return $value;
        }
    }

    public function getDefaultValue($key)
    {
        if (array_key_exists($key, $this->defaults)) {
            return $this->defaults[$key];
        } else {
            return null;
        }
    }

    public function getStoredOrDefaultValue($key)
    {
        $value = $this->getStoredValue($key);
        if ($value === null) {
            return $this->getDefaultValue($key);
        } else {
            return $value;
        }
    }

    public function set($name, $value)
    {
        $db = $this->db;

        if ($value === null) {
            $db->delete(
                'director_setting',
                $db->quoteInto('setting_name = ?', $name)
            );

            unset($this->cache[$name]);

            return $this;
        }

        if ($this->getSetting($name) === $value) {
            return $this;
        }

        $updated = $db->update(
            'director_setting',
            array('setting_value' => $value),
            $db->quoteInto('setting_name = ?', $name)
        );

        if ($updated === 0) {
            $db->insert(
                'director_setting',
                array(
                    'setting_name'  => $name,
                    'setting_value' => $value,
                )
            );
        }

        if ($this->cache !== null) {
            $this->cache[$name] = $value;
        }

        return $this;
    }

    public function clearCache()
    {
        $this->cache = null;
        return $this;
    }

    protected function getSetting($name, $default = null)
    {
        if ($this->cache === null) {
            $this->refreshCache();
        }

        if (array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        return $default;
    }

    protected function refreshCache()
    {
        $db = $this->db;

        $query = $db->select()->from(
            array('s' => 'director_setting'),
            array('setting_name', 'setting_value')
        );

        $this->cache = (array) $db->fetchPairs($query);
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function __destruct()
    {
        $this->clearCache();
        unset($this->db);
        unset($this->connection);
    }
}
