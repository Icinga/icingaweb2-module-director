<?php

namespace Tests\Icinga\Module\Director\RestApi;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\RestApi\IcingaObjectHandler;
use Icinga\Module\Director\Test\BaseTestCase;
use InvalidArgumentException;

class IcingaObjectHandlerTest extends BaseTestCase
{
    public function testDeleteIsAllowedOnTheIndexAction(): void
    {
        IcingaObjectHandler::assertDeleteAllowed('index');
        $this->addToAssertionCount(1);
    }

    public function testDeleteIsRejectedOnTheVariablesAction(): void
    {
        $this->expectException(NotFoundError::class);
        IcingaObjectHandler::assertDeleteAllowed('variables');
    }

    public function testJsonObjectBodyIsAccepted(): void
    {
        IcingaObjectHandler::assertJsonBodyIsObject((object) ['environment' => 'production']);
        $this->addToAssertionCount(1);
    }

    public function testJsonArrayBodyIsRejected(): void
    {
        // InvalidArgumentException is what processApiRequest() maps to HTTP 422,
        // the same status every other malformed override in this handler returns.
        $this->expectException(InvalidArgumentException::class);
        IcingaObjectHandler::assertJsonBodyIsObject([1, 2, 3]);
    }
}
