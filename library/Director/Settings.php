<?php

namespace Icinga\Module\Director;

class Settings
{
    protected $connection;

    protected $db;

    protected $cache;

    protected $defaults = [
        'default_global_zone'              => 'director-global',
        'icinga_package_name'              => 'director',
        'config_format'                    => 'v2',
        'override_services_varname'        => '_override_servicevars',
        'override_services_templatename'   => 'host var overrides (Director)',
        'disable_all_jobs'                 => 'n', // 'y'
        'enable_audit_log'                 => 'n',
        'deployment_mode_v1'               => 'active-passive',
        'deployment_path_v1'               => null,
        'activation_script_v1'             => null,
        'self-service/agent_name'          => 'fqdn',
        'self-service/agent_version'       => 'latest',
        'self-service/transform_hostname'  => '0',
        'self-service/resolve_parent_host' => '0',
        'self-service/global_zones'        => ['director-global'],
        'ignore_bug7530'                   => 'n',
        'feature_custom_endpoint'          => 'n',
        // 'experimental_features'        => null, // 'allow'
        // 'master_zone'                  => null,
    ];

    protected $jsonEncode = [
        'self-service/global_zones',
        'self-service/installer_hashes',
    ];

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    /**
     * @return Db
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return \Zend_Db_Adapter_Abstract
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        if (null === ($value = $this->getStoredValue($key, $default))) {
            return $this->getDefaultValue($key);
        } else {
            return $value;
        }
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed|null
     */
    public function getStoredValue($key, $default = null)
    {
        if (null === ($value = $this->getSetting($key))) {
            return $default;
        } else {
            if (in_array($key, $this->jsonEncode)) {
                $value = json_decode($value);
            }
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

    /**
     * @param $name
     * @param $value
     * @return $this
     * @throws \Zend_Db_Adapter_Exception
     */
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

        if (in_array($name, $this->jsonEncode)) {
            $value = json_encode(array_values($value));
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

    /**
     * @param $key
     * @return mixed|null
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * @param $key
     * @param $value
     * @throws \Zend_Db_Adapter_Exception
     */
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
