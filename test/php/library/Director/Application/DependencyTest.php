<?php

namespace Tests\Icinga\Module\Director\Application;

use Icinga\Module\Director\Application\Dependency;
use Icinga\Module\Director\Test\BaseTestCase;

class DependencyTest extends BaseTestCase
{
    public function testIsNotInstalled()
    {
        $dependency = new Dependency('something', '>=0.3.0');
        $this->assertFalse($dependency->isInstalled());
    }

    public function testNotSatisfiedWhenNotInstalled()
    {
        $dependency = new Dependency('something', '>=0.3.0');
        $this->assertFalse($dependency->isSatisfied());
    }

    public function testIsInstalled()
    {
        $dependency = new Dependency('something', '>=0.3.0');
        $dependency->setInstalledVersion('1.10.0');
        $this->assertTrue($dependency->isInstalled());
    }

    public function testNotEnabled()
    {
        $dependency = new Dependency('something', '>=0.3.0');
        $this->assertFalse($dependency->isEnabled());
    }

    public function testIsEnabled()
    {
        $dependency = new Dependency('something', '>=0.3.0');
        $dependency->setEnabled();
        $this->assertTrue($dependency->isEnabled());
    }

    public function testNotSatisfiedWhenNotEnabled()
    {
        $dependency = new Dependency('something', '>=0.3.0');
        $dependency->setInstalledVersion('1.10.0');
        $this->assertFalse($dependency->isSatisfied());
    }

    public function testSatisfiedWhenEqual()
    {
        $dependency = new Dependency('something', '>=0.3.0');
        $dependency->setInstalledVersion('0.3.0');
        $dependency->setEnabled();
        $this->assertTrue($dependency->isSatisfied());
    }

    public function testSatisfiedWhenGreater()
    {
        $dependency = new Dependency('something', '>=0.3.0');
        $dependency->setInstalledVersion('0.10.0');
        $dependency->setEnabled();
        $this->assertTrue($dependency->isSatisfied());
    }

    public function testNotSatisfiedWhenSmaller()
    {
        $dependency = new Dependency('something', '>=20.3.0');
        $dependency->setInstalledVersion('4.999.999');
        $dependency->setEnabled();
        $this->assertFalse($dependency->isSatisfied());
    }
}
