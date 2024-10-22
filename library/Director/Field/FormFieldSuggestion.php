<?php

namespace Icinga\Module\Director\Field;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Director\Objects\IcingaCommand;

class FormFieldSuggestion
{
    use TranslationHelper;

    /**
     * Macro/Argument names used in command argument values
     *
     * @var array
     */
    protected $argumentVars = [];
    protected $suggestedFields = [];
    protected $blacklistedVars = [];
    protected $descriptions = [];
    protected $booleans = [];

    /** @var ?IcingaCommand */
    protected $command;

    /** @var array */
    protected $existingFields;

    protected $fields = null;

    public function __construct(
        ?IcingaCommand $command,
        array $existingFields
    ) {
        $this->command = $command;
        $this->existingFields = $existingFields;
    }

    public function getCommandFields(): array
    {
        if ($this->fields === null) {
            $this->fields = $this->prepareFields();
        }

        return $this->fields;
    }

    protected function prepareFields(): array
    {
        // TODO: remove assigned ones!

        foreach ($this->existingFields as $id => $field) {
            if (preg_match('/ \(([^)]+)\)$/', $field, $m)) {
                $this->blacklistedVars['$' . $m[1] . '$'] = $id;
            }
        }

        if ($this->command) {
            foreach ($this->command->arguments() as $arg) {
                if ($arg->argument_format === 'string') {
                    foreach (self::extractMacroNamesFromString($arg->argument_value) as $val) {
                        $this->addSuggestion($val, $arg->description, $this->argumentVars);
                    }
                }

                if (
                    ($arg->set_if_format === 'string' || $arg->set_if_format === null)
                    && $val = self::getMacroIfStringIsSingleMacro($arg->set_if)
                ) {
                    $this->addSuggestion($val, $arg->description, $this->booleans);
                }
            }
        }

        asort($this->suggestedFields, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($this->argumentVars);
        ksort($this->booleans);
        asort($this->existingFields, SORT_NATURAL | SORT_FLAG_CASE);

        // Prepare combined fields array
        $fields = [];
        if (! empty($this->suggestedFields)) {
            $fields[$this->translate('Suggested fields')] = $this->suggestedFields;
        }

        if (! empty($this->argumentVars)) {
            $fields[$this->translate('Argument macros')] = $this->argumentVars;
        }

        if (! empty($this->booleans)) {
            $fields[$this->translate('Toggles (boolean arguments)')] = $this->booleans;
        }

        if (! empty($this->existingFields)) {
            $fields[$this->translate('Other available fields')] = $this->existingFields;
        }

        return $fields;
    }

    public function getDescription($id)
    {
        if (array_key_exists($id, $this->descriptions)) {
            return $this->descriptions[$id];
        }

        return null;
    }

    public function isBoolean(string $macro): bool
    {
        return isset($this->booleans[$macro]);
    }

    protected function addSuggestion(
        string $val,
        ?string $description,
        array &$targetList
    ) {
        if (array_key_exists($val, $this->blacklistedVars)) {
            $id = $this->blacklistedVars[$val];

            // Hint: if not set it might already have been
            //       removed in this loop
            if (array_key_exists($id, $this->existingFields)) {
                $this->suggestedFields[$id] = $this->existingFields[$id];
                unset($this->existingFields[$id]);
            }
        } else {
            $targetList[$val] = $val;
            $this->descriptions[$val] = $description;
        }
    }

    /**
     * Returns a macro name string ($macro_name$), if the given string is such, null otherwise
     *
     * @param ?string $string
     * @return ?string
     */
    protected static function getMacroIfStringIsSingleMacro(?string $string): ?string
    {
        if ($string === null) {
            return null;
        }

        if (preg_match('/^(\$[a-z0-9_]+\$)$/i', $string, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extracts all macro names ($macro_name$) from a given string
     *
     * @param ?string $string
     * @return array
     */
    protected static function extractMacroNamesFromString(?string $string): array
    {
        if ($string !== null && preg_match_all('/(\$[a-z0-9_]+\$)/i', $string, $matches, PREG_PATTERN_ORDER)) {
            return $matches[1];
        }

        return [];
    }
}
