<?php

namespace Tests\Icinga\Module\Director\Field;

use Icinga\Module\Director\Forms\IcingaObjectFieldForm;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Objects\IcingaCommand;

class IcingaObjectFieldFormTest extends BaseTestCase
{
    /** @var string */
    protected const COMMAND_NAME = 'icingaTestCommand';

    /** @var string */
    protected const DATAFIELD_NAME = 'dataFieldTest';

    /** @var string */
    protected const HOST_NAME = 'testHost';

    /** @var ?DirectorDatafield */
    protected $testDatafield = null;

    /** @var ?IcingaCommand */
    protected $testIcingaCommand = null;

    /** @var IcingaHost */
    private $testHost;

    public function setUp(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $this->testDatafield = DirectorDatafield::create([
                'varname'       => self::DATAFIELD_NAME,
                'caption'       => 'Blah',
                'description'   => '',
                'datatype'      => 'Icinga\Module\Director\DataType\DataTypeArray',
                'format'        => 'json'
            ]);
            $this->testDatafield->store($db);

            $this->testIcingaCommand = IcingaCommand::create(
                [
                    'object_name' => self::COMMAND_NAME,
                    'object_type' => 'object'
                ],
                $db
            );
            $this->testIcingaCommand->store($db);
            
            $this->testHost = IcingaHost::create([
                'object_name'  => self::HOST_NAME,
                'object_type'  => 'object',
                'display_name' => 'Whatever'
            ], $this->getDb());
        }
    }

    public function testFieldSuggestionsWithoutCheckCommand()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $icingaHostFieldForm = (new IcingaObjectFieldForm())
            ->setDb($db)
            ->setIcingaObject($this->testHost);

        $icingaHostFieldForm->setup();
        /** @var array<string> $suggestions */
        $suggestions = $icingaHostFieldForm->getElement('datafield_id')
            ->getAttrib('options');

        array_shift($suggestions);

        $this->assertEquals(
            [
                'Other available fields' => [
                    $this->testDatafield->get('id') => "Blah (dataFieldTest)"
                ]
            ],
            $suggestions
        );
    }

    public function testFieldSuggestionsWithCheckCommand()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $this->testHost->check_command = self::COMMAND_NAME;
        $icingaHostFieldForm = (new IcingaObjectFieldForm())
            ->setDb($db)
            ->setIcingaObject($this->testHost);

        $icingaHostFieldForm->setup();

        /** @var array<string> $suggestions */
        $suggestions = $icingaHostFieldForm->getElement('datafield_id')
            ->getAttrib('options');

        array_shift($suggestions);
        $this->assertEquals(
            [
                'Other available fields' => [
                    $this->testDatafield->get('id') => "Blah (dataFieldTest)"
                ]
            ],
            $suggestions
        );
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $this->deleteDatafields();

            if (IcingaHost::exists(self::HOST_NAME, $db)) {
                IcingaHost::load(self::HOST_NAME, $db)->delete();
            }

            if (IcingaCommand::exists(self::COMMAND_NAME, $db)) {
                IcingaCommand::load(self::COMMAND_NAME, $db)->delete();
            }
        }

        parent::tearDown();
    }

    protected function deleteDatafields()
    {
        $db = $this->getDb();
        $dbAdapter = $db->getDbAdapter();

        $query = $dbAdapter->select()
            ->from('director_datafield')
            ->where('varname = ?', self::DATAFIELD_NAME);
        foreach (DirectorDatafield::loadAll($db, $query, 'id') as $datafield) {
            $datafield->delete();
        }
    }
}
