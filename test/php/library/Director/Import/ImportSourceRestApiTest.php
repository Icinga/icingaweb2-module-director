<?php

namespace Tests\Icinga\Module\Director\Import;

use Icinga\Module\Director\Import\ImportSourceRestApi;
use Icinga\Module\Director\Test\BaseTestCase;

class ImportSourceRestApiTest extends BaseTestCase
{
    public function testExtractProperty()
    {
        $examples = [
            ''                     => json_decode('[{"name":"blau"}]'),
            'objects'              => json_decode('{"objects":[{"name":"blau"}]}'),
            'results.objects.all'  => json_decode('{"results":{"objects":{"all":[{"name":"blau"}]}}}'),
            'results\.objects.all' => json_decode('{"results.objects":{"all":[{"name":"blau"}]}}'),
        ];

        $source = new ImportSourceRestApi();

        foreach ($examples as $property => $data) {
            $source->setSettings(['extract_property' => $property]);
            $result = static::callMethod($source, 'extractProperty', [$data]);

            $this->assertCount(1, $result);
            $this->assertArrayHasKey('name', (array) $result[0]);
        }
    }
}
