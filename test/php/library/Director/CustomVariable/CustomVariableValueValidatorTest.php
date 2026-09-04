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
        CustomVariableValueValidator::assertMatchesType('env', ['staging', 'production'], 'string');
    }

    public function testStringValueAcceptsNumericString(): void
    {
        CustomVariableValueValidator::assertMatchesType('timeout', '30', 'number');
        $this->addToAssertionCount(1);
    }

    public function testArrayValueRejectsDictionary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType(
            'ssh_args',
            (object) ['StrictHostKeyChecking' => 'no'],
            'dynamic-array'
        );
    }

    public function testArrayValueAcceptsList(): void
    {
        CustomVariableValueValidator::assertMatchesType('ssh_args', ['-4', '-C'], 'fixed-array');
        $this->addToAssertionCount(1);
    }

    public function testDictionaryValueRejectsList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType('mysql', ['3306', 'root'], 'dynamic-dictionary');
    }

    public function testDictionaryValueAcceptsObject(): void
    {
        CustomVariableValueValidator::assertMatchesType(
            'mysql',
            (object) ['host' => 'db01.example.com'],
            'fixed-dictionary'
        );
        $this->addToAssertionCount(1);
    }

    // Without a property to check the item type against, a datalist just takes
    // either shape, only a dictionary is rejected outright.
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

    // With a property given, its item type child decides the shape, same as a
    // plain dynamic-array does. No item type child means a single value only.
    public function testDatalistWithArrayItemTypeRejectsScalarValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeDatalistStrictProperty(
            $db,
            'validator_array_item_scalar',
            'validator_array_item_scalar_list',
            ['prod', 'dev'],
            true
        );

        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType(
            'validator_array_item_scalar',
            'prod',
            'datalist-strict',
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
    }

    public function testDatalistWithArrayItemTypeAcceptsArray(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeDatalistStrictProperty(
            $db,
            'validator_array_item_ok',
            'validator_array_item_ok_list',
            ['prod', 'dev'],
            true
        );

        CustomVariableValueValidator::assertMatchesType(
            'validator_array_item_ok',
            ['prod', 'dev'],
            'datalist-strict',
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
        $this->addToAssertionCount(1);
    }

    public function testDatalistWithoutItemTypeRejectsArrayValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeDatalistStrictProperty(
            $db,
            'validator_scalar_item_array',
            'validator_scalar_item_array_list',
            ['prod', 'dev']
        );

        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType(
            'validator_scalar_item_array',
            ['prod', 'dev'],
            'datalist-strict',
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
    }

    public function testDatalistWithoutItemTypeAcceptsScalarValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeDatalistStrictProperty(
            $db,
            'validator_scalar_item_ok',
            'validator_scalar_item_ok_list',
            ['prod', 'dev']
        );

        CustomVariableValueValidator::assertMatchesType(
            'validator_scalar_item_ok',
            'prod',
            'datalist-strict',
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
        $this->addToAssertionCount(1);
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
        array $entryNames,
        bool $withArrayItemType = false
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

        if ($withArrayItemType) {
            DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => '0',
                'parent_uuid' => $property->get('uuid'),
                'value_type'  => 'dynamic-array',
            ], $db)->store();
        }

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
