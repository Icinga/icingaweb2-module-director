<?php

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomVariableValueValidator;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Test\BaseTestCase;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class CustomVariableValueValidatorTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    public function testStringValueRejectsArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType('env', ['a', 'b'], 'string');
    }

    public function testStringValueAcceptsNumericString(): void
    {
        CustomVariableValueValidator::assertMatchesType('timeout', '30', 'number');
        $this->addToAssertionCount(1);
    }

    public function testArrayValueRejectsDictionary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType('ssh_args', (object) ['a' => 'b'], 'dynamic-array');
    }

    public function testArrayValueAcceptsList(): void
    {
        CustomVariableValueValidator::assertMatchesType('ssh_args', ['a', 'b'], 'fixed-array');
        $this->addToAssertionCount(1);
    }

    public function testDictionaryValueRejectsList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType('mysql', ['a', 'b'], 'dynamic-dictionary');
    }

    public function testDictionaryValueAcceptsObject(): void
    {
        CustomVariableValueValidator::assertMatchesType('mysql', (object) ['host' => 'db'], 'fixed-dictionary');
        $this->addToAssertionCount(1);
    }

    public function testDatalistTypeAcceptsScalarValue(): void
    {
        CustomVariableValueValidator::assertMatchesType('env_choice', 'prod', 'datalist-strict');
        $this->addToAssertionCount(1);
    }

    public function testDatalistTypeAcceptsArrayOfScalars(): void
    {
        CustomVariableValueValidator::assertMatchesType('env_choices', ['prod', 'dev'], 'datalist-non-strict');
        $this->addToAssertionCount(1);
    }

    public function testDatalistTypeRejectsDictionary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType('env_choice', (object) ['a' => 'b'], 'datalist-strict');
    }

    public function testDatalistStrictRejectsUnknownValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeDatalistStrictProperty(
            $db,
            'validator_env_choice',
            'validator_env',
            ['prod', 'dev']
        );

        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertDatalistValueAllowed(
            'validator_env_choice',
            'staging',
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
    }

    public function testDatalistStrictAcceptsKnownValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeDatalistStrictProperty(
            $db,
            'validator_env_choice_ok',
            'validator_env_ok',
            ['prod']
        );

        CustomVariableValueValidator::assertDatalistValueAllowed(
            'validator_env_choice_ok',
            'prod',
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
        $this->addToAssertionCount(1);
    }

    public function testDatalistStrictAcceptsArrayOfKnownValues(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeDatalistStrictProperty(
            $db,
            'validator_env_choices_array',
            'validator_env_array',
            ['prod', 'dev']
        );

        CustomVariableValueValidator::assertDatalistValueAllowed(
            'validator_env_choices_array',
            ['prod', 'dev'],
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
        $this->addToAssertionCount(1);
    }

    public function testDatalistStrictRejectsArrayContainingAnUnknownValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeDatalistStrictProperty(
            $db,
            'validator_env_choices_bad_array',
            'validator_env_bad_array',
            ['prod', 'dev']
        );

        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertDatalistValueAllowed(
            'validator_env_choices_bad_array',
            ['prod', 'staging'],
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
    }

    /**
     * @param string[] $entryNames
     */
    private function makeDatalistStrictProperty(
        $db,
        string $keyName,
        string $listName,
        array $entryNames
    ): DirectorProperty {
        $listName = self::PREFIX . $listName;
        $keyName = self::PREFIX . $keyName;

        $datalist = DirectorDatalist::create(['list_name' => $listName, 'owner' => 'test'], $db);
        $datalist->setEntries(array_map(
            fn ($name) => (object) ['entry_name' => $name, 'entry_value' => ucfirst($name)],
            $entryNames
        ));
        $datalist->store();

        $plain = (object) [
            'uuid'        => Uuid::uuid4()->toString(),
            'key_name'    => $keyName,
            'value_type'  => 'datalist-strict',
            'label'       => 'Environment',
            'parent_uuid' => null,
            'category'    => null,
            'description' => null,
            'datalist'    => $listName,
            'items'       => [],
        ];
        $property = DirectorProperty::import($plain, $db);
        $property->store();

        return $property;
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();
            $dba->delete('director_property', $dba->quoteInto('key_name LIKE ?', self::PREFIX . 'validator_%'));
            $dba->delete('director_datalist', $dba->quoteInto('list_name LIKE ?', self::PREFIX . 'validator_%'));
        }

        parent::tearDown();
    }
}
