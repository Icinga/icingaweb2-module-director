<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierSimpleGroupBy extends PropertyModifierHook
{
    private $keptRows = [];

    public function getName()
    {
        return mt('director', 'Group by a column, aggregate others');
    }

    public function requiresRow()
    {
        return true;
    }

    public function transform($value)
    {
        $row = $this->getRow();
        $aggregationColumns = preg_split(
            '/\s*,\s*/',
            $this->getSetting('aggregation_columns'),
            -1,
            PREG_SPLIT_NO_EMPTY
        );
        if (isset($this->keptRows[$value])) {
            foreach ($aggregationColumns as $column) {
                if (isset($row->$column)) {
                    $values = array_merge(
                        $this->keptRows[$value]->{$column},
                        [$row->$column]
                    );
                    $hasStructuredValues = false;
                    foreach ($values as $item) {
                        if (is_array($item) || is_object($item)) {
                            $hasStructuredValues = true;
                            break;
                        }
                    }

                    if ($hasStructuredValues) {
                        // array_unique() casts values to strings and fails for objects.
                        // Keep structured values in import order and compare their contents.
                        $unique = [];
                        foreach ($values as $item) {
                            $duplicate = false;
                            foreach ($unique as $previous) {
                                if (is_array($item) || is_object($item)
                                    || is_array($previous) || is_object($previous)
                                ) {
                                    $equal = gettype($item) === gettype($previous)
                                        && $item == $previous;
                                } else {
                                    $equal = (string) $item === (string) $previous;
                                }
                                if ($equal) {
                                    $duplicate = true;
                                    break;
                                }
                            }
                            if (! $duplicate) {
                                $unique[] = $item;
                            }
                        }
                        $this->keptRows[$value]->{$column} = $unique;
                    } else {
                        $this->keptRows[$value]->{$column} = array_unique($values);
                        sort($this->keptRows[$value]->{$column});
                    }
                }
            }
            $this->rejectRow();
        } else {
            foreach ($aggregationColumns as $column) {
                if (isset($row->$column)) {
                    $row->$column = [$row->$column];
                } else {
                    $row->$column = [];
                }
            }

            $this->keptRows[$value] = $row;
        }

        return $value;
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'aggregation_columns', [
            'label'       => $form->translate('Aggregation Columns'),
            'description' => $form->translate(
                'Comma-separated list of columns that should be aggregated (transformed into an Array).'
                . ' For all other columns only the first value will be kept.'
            ),
            'required'    => true,
        ]);
    }
}
