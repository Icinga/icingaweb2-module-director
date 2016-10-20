<?php

namespace Tests\Icinga\Module\Director\DataType;

use Icinga\Module\Director\DataType\DataTypeDictionary;
use Icinga\Module\Director\Objects\DirectorDictionary;
use Icinga\Module\Director\Objects\DirectorDictionaryField;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;


class DataTypeDictionaryTest extends BaseTestCase
{
    private $dataType;
    private $dictionaries = [];
    private $dictionaryFields = [];

    public function setUp() {
        parent::setUp();
        $this->dataType = new DataTypeDictionary();

        $this->skipForMissingDb();
    }

    public function tearDown() {
        if ($this->hasDb()) {
            foreach ($this->dictionaryFields as $dictionaryField) {
                DirectorDictionaryField::load($dictionaryField->getId(), $this->getDb())->delete();
            }
            $this->dictionaryFields = [];

            foreach ($this->dictionaries as $dictionary) {
                DirectorDictionary::load($dictionary->getId(), $this->getDb())->delete();
            }
            $this->dictionaries = [];
        }
    }

    public function testGetDictionaryFormElementWithoutFields() {
        $this->havingEmptyDictionary();

        $element = $this->getTestedElement();

        $this->assertEquals('{}', json_encode($element->getValue(), JSON_FORCE_OBJECT));
    }

    public function testGetDictionaryFormElementWithFields() {
        $this->havingDictionaryWithFields();

        $element = $this->getTestedElement();

        $this->assertEquals('{"foobar-arr":[],"foobar-dict":null,"foobar-number":0,"foobar-str":""}', json_encode($element->getValue()));
    }

    public function testGetDictionaryFormElementWithRecursion() {
        $this->havingDictionaryWithRecursion();

        $element = $this->getTestedElement();

        $this->assertEquals('{"foobar-sub-dict":{"foobar-sub-string":""}}', json_encode($element->getValue()));
    }

    public function testGetDictionaryFieldSettingsMap() {
        $this->havingDictionaryWithRecursion();

        $element = $this->getTestedElement();

        $fieldSettingsMap = $element->getFieldSettingsMap();

        $this->assertEquals(2, count(array_keys($fieldSettingsMap)));
        $this->assertTrue($fieldSettingsMap['foobar-sub-dict']['is_required']);
        $this->assertFalse($fieldSettingsMap['foobar-sub-dict.foobar-sub-string']['is_required']);
    }

    private function getTestedElement() {
        $quickForm = new TestQuickForm($this);
        $quickForm->setDb($this->getDb());

        $this->dataType->setSettings([
            'reference_id' => $this->dictionaries[0]->getId()
        ]);

        return $this->dataType->getFormElement('testName', $quickForm);
    }

    private function havingDictionaryWithFields() {
        $tempDictionary = DirectorDictionary::create([
            'dictionary_name' => 'foobaz',
            'owner' => 'icingaadmin'
        ]);
        $tempDictionary->store($this->getDb());
        array_push($this->dictionaries, $tempDictionary);

        $tempDictionaryField = DirectorDictionaryField::create([
            'dictionary_id' => $tempDictionary->getId(),
            'varname' => 'foobar-str',
            'caption' => 'FOOBAR STRING',
            'datatype' => 'Icinga\\Module\\Director\\DataType\\DataTypeString',
            'is_required' => 'n',
            'allow_multiple' => 'n'
        ]);
        $tempDictionaryField->store($this->getDb());
        array_push($this->dictionaryFields, $tempDictionaryField);

        $tempDictionaryField = DirectorDictionaryField::create([
            'dictionary_id' => $tempDictionary->getId(),
            'varname' => 'foobar-number',
            'caption' => 'FOOBAR NUMBER',
            'datatype' => 'Icinga\\Module\\Director\\DataType\\DataTypeNumber',
            'is_required' => 'n',
            'allow_multiple' => 'n'
        ]);
        $tempDictionaryField->store($this->getDb());
        array_push($this->dictionaryFields, $tempDictionaryField);

        $tempDictionaryField = DirectorDictionaryField::create([
            'dictionary_id' => $tempDictionary->getId(),
            'varname' => 'foobar-arr',
            'caption' => 'FOOBAR ARRAY',
            'datatype' => 'Icinga\\Module\\Director\\DataType\\DataTypeArray',
            'is_required' => 'n',
            'allow_multiple' => 'n'
        ]);
        $tempDictionaryField->store($this->getDb());
        array_push($this->dictionaryFields, $tempDictionaryField);

        $tempDictionaryField = DirectorDictionaryField::create([
            'dictionary_id' => $tempDictionary->getId(),
            'varname' => 'foobar-dict',
            'caption' => 'FOOBAR DICTIONARY',
            'datatype' => 'Icinga\\Module\\Director\\DataType\\DataTypeDictionary',
            'is_required' => 'n',
            'allow_multiple' => 'n'
        ]);
        $tempDictionaryField->store($this->getDb());
        array_push($this->dictionaryFields, $tempDictionaryField);
    }

    private function havingDictionaryWithRecursion() {
        $tempDictionary = DirectorDictionary::create([
            'dictionary_name' => 'foobaz',
            'owner' => 'icingaadmin'
        ]);
        $tempDictionary->store($this->getDb());
        $parentDictId = $tempDictionary->getId();
        array_push($this->dictionaries, $tempDictionary);

        $tempDictionary = DirectorDictionary::create([
            'dictionary_name' => 'sub-foobaz',
            'owner' => 'icingaadmin'
        ]);
        $tempDictionary->store($this->getDb());
        $subDictId = $tempDictionary->getId();
        array_push($this->dictionaries, $tempDictionary);

        $tempDictionaryField = DirectorDictionaryField::create([
            'dictionary_id' => $parentDictId,
            'varname' => 'foobar-sub-dict',
            'caption' => 'FOOBAR SUD DICT',
            'datatype' => 'Icinga\\Module\\Director\\DataType\\DataTypeDictionary',
            'is_required' => 'y',
            'allow_multiple' => 'n',
            'reference_id' => $subDictId
        ]);
        $tempDictionaryField->store($this->getDb());
        array_push($this->dictionaryFields, $tempDictionaryField);

        $tempDictionaryField = DirectorDictionaryField::create([
            'dictionary_id' => $subDictId,
            'varname' => 'foobar-sub-string',
            'caption' => 'FOOBAR SUB STRING',
            'datatype' => 'Icinga\\Module\\Director\\DataType\\DataTypeString',
            'is_required' => 'n',
            'allow_multiple' => 'n'
        ]);
        $tempDictionaryField->store($this->getDb());
        array_push($this->dictionaryFields, $tempDictionaryField);
    }

    private function havingEmptyDictionary() {
        $tempDictionary = DirectorDictionary::create([
            'dictionary_name' => 'foobaz',
            'owner' => 'icingaadmin'
        ]);
        $tempDictionary->store($this->getDb());
        array_push($this->dictionaries, $tempDictionary);
    }
}

class TestQuickForm extends DirectorObjectForm {
    public function __construct($options)
    {
        parent::__construct($options);
        $this->addPrefixPaths(array(
            array(
                'prefix'    => 'Icinga\\Module\\Director\\Web\\Form\\Element\\',
                'path'      => '/usr/share/icingaweb2/modules/director/library/Director/Web/Form/Element',
                'type'      => static::ELEMENT
            )
        ));
    }
}
