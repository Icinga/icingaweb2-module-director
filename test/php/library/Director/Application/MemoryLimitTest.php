<?php

namespace Tests\Icinga\Module\Director\Application;

use Icinga\Module\Director\Application\MemoryLimit;
use Icinga\Module\Director\Test\BaseTestCase;

class MemoryLimitTest extends BaseTestCase
{
    public function testBytesValuesAreHandled()
    {
        $this->assertTrue(is_int(MemoryLimit::parsePhpIniByteString('1073741824')));
        $this->assertEquals(
            1073741824,
            MemoryLimit::parsePhpIniByteString('1073741824')
        );
    }

    public function testIntegersAreAccepted()
    {
        $this->assertEquals(
            MemoryLimit::parsePhpIniByteString(1073741824),
            1073741824
        );
    }

    public function testNoLimitGivesMinusOne()
    {
        $this->assertTrue(is_int(MemoryLimit::parsePhpIniByteString('-1')));
        $this->assertEquals(
            -1,
            MemoryLimit::parsePhpIniByteString('-1')
        );
    }

    public function testInvalidStringGivesBytes()
    {
        $this->assertEquals(
            1024,
            MemoryLimit::parsePhpIniByteString('1024MB')
        );
    }

    public function testHandlesKiloBytes()
    {
        $this->assertEquals(
            45 * 1024,
            MemoryLimit::parsePhpIniByteString('45K')
        );
    }

    public function testHandlesMegaBytes()
    {
        $this->assertEquals(
            512 * 1024 * 1024,
            MemoryLimit::parsePhpIniByteString('512M')
        );
    }

    public function testHandlesGigaBytes()
    {
        $this->assertEquals(
            2 * 1024 * 1024 * 1024,
            MemoryLimit::parsePhpIniByteString('2G')
        );
    }
}
