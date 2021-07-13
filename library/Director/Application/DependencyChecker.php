<?php

namespace Icinga\Module\Director\Application;

use Icinga\Application\ApplicationBootstrap;
use Icinga\Application\Modules\Module;
use Icinga\Application\Version;

class DependencyChecker
{
    /** @var ApplicationBootstrap */
    protected $app;

    /** @var \Icinga\Application\Modules\Manager */
    protected $modules;

    public function __construct(ApplicationBootstrap $app)
    {
        $this->app = $app;
        $this->modules = $app->getModuleManager();
    }

    /**
     * @param Module $module
     * @return Dependency[]
     */
    public function getDependencies(Module $module)
    {
        $dependencies = [];
        $isV290 = version_compare(Version::VERSION, '2.9.0', '>=');
        foreach ($module->getDependencies() as $moduleName => $required) {
            if ($isV290 && in_array($moduleName, ['ipl', 'reactbundle'], true)) {
                continue;
            }
            $dependency = new Dependency($moduleName, $required);
            $dependency->setEnabled($this->modules->hasEnabled($moduleName));
            if ($this->modules->hasInstalled($moduleName)) {
                $dependency->setInstalledVersion($this->modules->getModule($moduleName, false)->getVersion());
            }
            $dependencies[] = $dependency;
        }
        if ($isV290) {
            $libs = $this->app->getLibraries();
            foreach ($module->getRequiredLibraries() as $libraryName => $required) {
                $dependency = new Dependency($libraryName, $required);
                if ($libs->has($libraryName)) {
                    $dependency->setInstalledVersion($libs->get($libraryName)->getVersion());
                    $dependency->setEnabled();
                }
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    //     if (version_compare(Version::VERSION, '2.9.0', 'ge')) {
    //    }
    /**
     * @param Module $module
     * @return bool
     */
    public function satisfiesDependencies(Module $module)
    {
        foreach ($this->getDependencies($module) as $dependency) {
            if (! $dependency->isSatisfied()) {
                return false;
            }
        }

        return true;
    }
}
