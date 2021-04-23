<?php

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
                    $this->keptRows[$value]->{$column} = array_unique(array_merge(
                        $this->keptRows[$value]->{$column},
                        [$row->$column]
                    ));
                    sort($this->keptRows[$value]->{$column});
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
