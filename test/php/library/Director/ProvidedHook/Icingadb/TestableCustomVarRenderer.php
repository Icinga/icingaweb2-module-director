<?php

namespace Tests\Icinga\Module\Director\ProvidedHook\Icingadb;

use Icinga\Module\Director\ProvidedHook\Icingadb\CustomVarRenderer;
use ipl\Html\ValidHtml;
use RuntimeException;

/**
 * Test seam: exposes protected rendering internals of CustomVarRenderer without
 * needing a real Icingadb Host/Service model to drive prefetchForObject().
 */
class TestableCustomVarRenderer extends CustomVarRenderer
{
    private bool $forceRenderFailure = false;

    public function seedPrefetchFailed(): void
    {
        $this->prefetchFailed = true;
    }

    public function forceRenderFailure(): void
    {
        $this->forceRenderFailure = true;
    }

    protected function renderDictionaryVal(string $key, array $value): ?ValidHtml
    {
        if ($this->forceRenderFailure) {
            throw new RuntimeException('forced failure for test');
        }

        return parent::renderDictionaryVal($key, $value);
    }

    public function seedDictionaryChild(
        string $parentKey,
        string $childKey,
        array $config,
        ?string $grandparentKey = null,
        ?string $valueType = null
    ): void {
        if ($valueType !== null) {
            $config['value_type'] = $valueType;
        }

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

    public function seedPropertyValueType(string $key, string $valueType): void
    {
        $this->customVariableConfig[$key]['value_type'] = $valueType;
    }

    public function seedCustomVariableLabel(string $key, string $label): void
    {
        $this->customVariableConfig[$key]['label'] = $label;
    }

    public function seedCustomPropertyDictionary(string $key, array $children = []): void
    {
        $this->customPropertyDictionaries[$key] = $children;
    }

    public function seedDatalistEntry(
        string $key,
        string $entryName,
        string $entryValue,
        ?string $parentKey = null,
        ?string $grandparentKey = null
    ): void {
        $scope = $this->ownScopeKey($key, $parentKey, $grandparentKey);
        $this->datalistMaps[$scope][$entryName] = $entryValue;
    }

    public function renderDictionaryValForTest(string $key, array $value)
    {
        return $this->renderDictionaryVal($key, $value);
    }
}
