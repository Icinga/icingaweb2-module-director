<?php

namespace Tests\Icinga\Module\Director\IcingaConfig;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\AssignFilterHelper;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Test\BaseTestCase;

class AssignFilterHelperTest extends BaseTestCase
{
    protected static $exampleHost;

    public static function setUpBeforeClass(): void
    {
        self::$exampleHost = (object) [
            'address'              => '127.0.0.1',
            'vars.operatingsystem' => 'centos',
            'vars.customer'        => 'ACME',
            'vars.roles'           => ['webserver', 'mailserver'],
            'vars.bool_string'     => 'true',
            'groups'               => ['web-server', 'mail-server'],
        ];
    }

    public function testSimpleApplyFilter()
    {
        $this->assertFilterOutcome(true, 'host.address=true', self::$exampleHost);
        $this->assertFilterOutcome(false, 'host.address=false', self::$exampleHost);
        $this->assertFilterOutcome(true, 'host.address=false', (object) ['address' => null]);
        $this->assertFilterOutcome(false, 'host.address=true', (object) ['address' => null]);
        $this->assertFilterOutcome(true, 'host.address=%22127.0.0.%2A%22', self::$exampleHost);
    }

    public function testListApplyFilter()
    {
        $this->assertFilterOutcome(true, 'host.vars.roles=%22*server%22', self::$exampleHost);
        $this->assertFilterOutcome(true, 'host.groups=%22*-server%22', self::$exampleHost);
        $this->assertFilterOutcome(false, 'host.groups=%22*-nothing%22', self::$exampleHost);
    }

    public function testComplexApplyFilter()
    {
        $this->assertFilterOutcome(
            true,
            'host.vars.operatingsystem=%5B%22centos%22%2C%22fedora%22%5D|host.vars.osfamily=%22redhat%22',
            self::$exampleHost
        );

        $this->assertFilterOutcome(
            false,
            'host.vars.operatingsystem=%5B%22centos%22%2C%22fedora%22%5D&(!(host.vars.customer=%22acme*%22))',
            self::$exampleHost
        );

        $this->assertFilterOutcome(
            true,
            '!(host.vars.bool_string="false")&host.vars.operatingsystem="centos"',
            self::$exampleHost
        );
    }

    /**
     * @param bool   $expected
     * @param string $filterQuery
     * @param object $object
     * @param string $message
     */
    protected function assertFilterOutcome($expected, $filterQuery, $object, $message = null, $type = 'host')
    {
        $filter = Filter::fromQueryString($filterQuery);

        if ($type === 'host') {
            HostApplyMatches::fixFilterColumns($filter);
        }

        $helper = new AssignFilterHelper($filter);
        $actual = $helper->matches($object);

        if ($message === null) {
            $message = sprintf('with filter "%s"', $filterQuery);
        }

        $this->assertEquals($expected, $actual, $message);
    }
}
