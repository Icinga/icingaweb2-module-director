<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaCommandTest extends BaseTestCase
{
    protected $testCommandName = '___TEST___command';

    public function testCommandsWithArgumentsCanBeCreated()
    {
        $command = $this->command();
        $command->arguments = array(
            '-H' => '$host$'
        );

        $this->assertEquals(
            '-H',
            $command->arguments()->get('-H')->argument_name
        );

        $this->assertEquals(
            '$host$',
            $command->toPlainObject()->arguments['-H']
        );

        $command->arguments()->get('-H')->required = true;
    }

    protected function command()
    {
        return IcingaCommand::create(
            array(
                'object_name' => $this->testCommandName,
                'object_type' => 'object'
            )
        );
    }

    public function XXtearDown()
    {
        $db = $this->getDb();
        if (IcingaTimePeriod::exists($this->testPeriodName, $db)) {
            IcingaTimePeriod::load($this->testPeriodName, $db)->delete();
        }
    }
}
