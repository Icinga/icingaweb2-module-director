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

    public function testCommandsWithArgumentsCanBeModified()
    {
        $command = $this->command();
        $command->arguments = array(
            '-H' => '$host$'
        );

        $command->arguments = array(
            '-x' => (object) array(
                'required' => true
            )
        );

        $this->assertEquals(
            null,
            $command->arguments()->get('-H')
        );

        $this->assertEquals(
            'y',
            $command->arguments()->get('-x')->get('required')
        );

        $this->assertEquals(
            true,
            $command->toPlainObject()->arguments['-x']->required
        );
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

    public function tearDown()
    {
        $db = $this->getDb();
        if (IcingaCommand::exists($this->testCommandName, $db)) {
            IcingaCommand::load($this->testCommandName, $db)->delete();
        }
    }
}
