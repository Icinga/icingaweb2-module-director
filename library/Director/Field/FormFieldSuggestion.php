<?php

namespace Icinga\Module\Director\Field;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Director\Objects\IcingaCommand;

class FormFieldSuggestion
{
    use TranslationHelper;

    public function getCommandFields(
        IcingaCommand $command,
        array $existingFields,
        array &$descriptions
    ): array
    {
        // TODO: remove assigned ones!
        $argumentVars = [];
        $blacklistedVars = [];
        $suggestedFields = [];
        $booleans = [];

        foreach ($existingFields as $id => $field) {
            if (preg_match('/ \(([^)]+)\)$/', $field, $m)) {
                $blacklistedVars['$' . $m[1] . '$'] = $id;
            }
        }
        foreach ($command->arguments() as $arg) {
            if ($arg->argument_format === 'string') {
                foreach (self::extractMacroNamesFromString($arg->argument_value) as $val) {
                    self::addSuggestion(
                        $val,
                        $arg->description,
                        $blacklistedVars,
                        $existingFields,
                        $suggestedFields,
                        $argumentVars,
                        $descriptions
                    );
                }
            }
        }

        // Prepare combined fields array
        $fields = [];
        if (! empty($suggestedFields)) {
            asort($suggestedFields, SORT_NATURAL | SORT_FLAG_CASE);
            $fields[$this->translate('Suggested fields')] = $suggestedFields;
        }

        if (! empty($argumentVars)) {
            ksort($argumentVars);
            $fields[$this->translate('Argument macros')] = $argumentVars;
        }

        if (! empty($existingFields)) {
            asort($existingFields, SORT_NATURAL | SORT_FLAG_CASE);
            $fields[$this->translate('Other available fields')] = $existingFields;
        }

        return $fields;
    }

    protected static function addSuggestion(
        string $val,
        ?string $description,
        array $blacklistedVars,
        array &$existingFields,
        array &$suggestedFields,
        array &$targetList,
        array &$descriptions
    ) {
        if (array_key_exists($val, $blacklistedVars)) {
            $id = $blacklistedVars[$val];

            // Hint: if not set it might already have been
            //       removed in this loop
            if (array_key_exists($id, $existingFields)) {
                $suggestedFields[$id] = $existingFields[$id];
                unset($existingFields[$id]);
            }
        } else {
            $targetList[$val] = $val;
            $descriptions[$val] = $description;
        }

    }

    protected static function extractMacroNamesFromString(?string $string): array
    {
        if ($string !== null && preg_match_all('/(\$[a-z0-9_]+\$)/i', $string, $matches, PREG_PATTERN_ORDER)) {
            return $matches[1];
        }

        return [];
    }
}
