<?php

namespace Icinga\Module\Director\Application;

class Dependency
{
    /** @var string */
    protected $name;

    /** @var string|null */
    protected $installedVersion;

    /** @var bool|null */
    protected $enabled;

    /** @var string */
    protected $operator;

    /** @var string */
    protected $requiredVersion;

    /** @var string */
    protected $requirement;

    /**
     * Dependency constructor.
     * @param string $name         Usually a module name
     * @param string $requirement  e.g. >=1.7.0
     * @param string $installedVersion
     * @param bool $enabled
     */
    public function __construct($name, $requirement, $installedVersion = null, $enabled = null)
    {
        $this->name = $name;
        $this->setRequirement($requirement);
        if ($installedVersion !== null) {
            $this->setInstalledVersion($installedVersion);
        }
        if ($enabled !== null) {
            $this->setEnabled($enabled);
        }
    }

    public function setRequirement($requirement)
    {
        if (preg_match('/^([<>=]+)\s*v?(\d+\.\d+\.\d+)$/', $requirement, $match)) {
            $this->operator = $match[1];
            $this->requiredVersion = $match[2];
            $this->requirement = $requirement;
        } else {
            throw new \InvalidArgumentException("'$requirement' is not a valid version constraint");
        }
    }

    /**
     * @return bool
     */
    public function isInstalled()
    {
        return $this->installedVersion !== null;
    }

    /**
     * @return string|null
     */
    public function getInstalledVersion()
    {
        return $this->installedVersion;
    }

    /**
     * @param string $version
     */
    public function setInstalledVersion($version)
    {
        $this->installedVersion = ltrim($version, 'v'); // v0.6.0 VS 0.6.0
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled === true;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled($enabled = true)
    {
        $this->enabled = $enabled;
    }

    public function isSatisfied()
    {
        if (! $this->isInstalled() || ! $this->isEnabled()) {
            return false;
        }

        return version_compare($this->installedVersion, $this->requiredVersion, $this->operator);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getRequirement()
    {
        return $this->requirement;
    }
}
