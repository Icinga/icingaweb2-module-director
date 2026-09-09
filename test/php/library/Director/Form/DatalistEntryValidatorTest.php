<?php

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\Validator\DatalistEntryValidator;
use Icinga\Module\Director\Test\BaseTestCase;
use LogicException;

class DatalistEntryValidatorTest extends BaseTestCase
{
    public function testIsValidRejectsMissingDatalistEntries(): void
    {
        $this->expectException(LogicException::class);

        (new DatalistEntryValidator())->isValid([]);
    }
}
