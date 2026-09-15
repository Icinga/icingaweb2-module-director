<?php

namespace Tests\Icinga\Module\Director\ProvidedHook\Icingadb;

use Icinga\Application\Logger\LogWriter;
use Icinga\Data\ConfigObject;

/**
 * Test log writer that just remembers what got logged instead of writing to stderr
 */
class CapturingLogWriter extends LogWriter
{
    /** @var string[] */
    private array $messages = [];

    public function __construct()
    {
        parent::__construct(new ConfigObject([]));
    }

    public function log($severity, $message): void
    {
        $this->messages[] = $message;
    }

    public function hasMessageContaining(string $needle): bool
    {
        foreach ($this->messages as $message) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
