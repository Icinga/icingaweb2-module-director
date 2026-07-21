<?php

namespace Tests\Icinga\Module\Director\ProvidedHook\Icingadb;

use Icinga\Module\Director\ProvidedHook\Icingadb\CustomVarRenderer;

/**
 * Test seam: exposes protected rendering internals of CustomVarRenderer without
 * needing a real Icingadb Host/Service model to drive prefetchForObject().
 */
class TestableCustomVarRenderer extends CustomVarRenderer
{
    public function seedDictionaryChild(
        string $parentKey,
        string $childKey,
        array $config,
        ?string $grandparentKey = null
    ): void {
        $this->dictionaryChildConfig[$this->scopeKey($grandparentKey, $parentKey)][$childKey] = $config;
    }

    public function seedSensitiveArrayItem(string $parentKey, string $childKey, ?string $grandparentKey = null): void
    {
        $this->sensitiveArrayItems[$this->scopeKey($grandparentKey, $parentKey)][$childKey] = true;
    }

    public function seedDictionaryName(string $key): void
    {
        $this->dictionaryNames[] = $key;
    }

    public function renderDictionaryValForTest(string $key, array $value)
    {
        return $this->renderDictionaryVal($key, $value);
    }
}
