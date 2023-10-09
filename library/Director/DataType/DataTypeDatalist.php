<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Objects\DirectorDatalistEntry;
use Icinga\Module\Director\Web\Form\DirectorForm;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Form\Validate\IsDataListEntry;

class DataTypeDatalist extends DataTypeHook
{
    /**
     * @param $name
     * @param QuickForm $form
     * @return \Zend_Form_Element
     * @throws \Zend_Form_Exception
     */
    public function getFormElement($name, QuickForm $form)
    {
        $params = [];
        $behavior = $this->getSetting('behavior', 'strict');
        $targetDataType = $this->getSetting('data_type', 'string');
        $listId = $this->getSetting('datalist_id');

        if ($behavior === 'strict') {
            $enum = $this->getEntries($form);
            if ($targetDataType === 'string') {
                $params['sorted'] = true;
                $params = ['multiOptions' => $form->optionalEnum($enum)];
                $type = 'select';
            } else {
                $params = ['multiOptions' => $form->optionalEnum($enum)];
                $type = 'extensibleSet';
            }
        } else {
            if ($targetDataType === 'string') {
                $type = 'text';
            } else {
                $type = 'extensibleSet';
            }
            $params['class'] = 'director-suggest';
            $params['data-suggestion-context'] = "dataListValuesForListId!$listId";
        }
        $element = $form->createElement($type, $name, $params);
        if ($behavior === 'suggest_strict') {
            $element->addValidator(new IsDataListEntry($listId, $form->getDb()));
        }

        if ($behavior === 'suggest_extend') {
            $form->callOnSuccess(function (DirectorForm $form) use ($name, $listId) {
                $value = (array) $form->getValue($name);
                if ($value === null) {
                    return;
                }

                $db = $form->getDb();
                foreach ($value as $entry) {
                    if ($entry !== '') {
                        $this->createEntryIfNotExists($db, $listId, $entry);
                    }
                }
            });
        }

        return $element;
    }

    /**
     * @param Db $db
     * @param $listId
     * @param $entry
     */
    protected function createEntryIfNotExists(Db $db, $listId, $entry)
    {
        if (! DirectorDatalistEntry::exists([
            'list_id'    => $listId,
            'entry_name' => $entry,
        ], $db)) {
            DirectorDatalistEntry::create([
                'list_id'     => $listId,
                'entry_name'  => $entry,
                'entry_value' => $entry,
            ])->store($db);
        }
    }

    protected function getEntries(QuickForm $form)
    {
        /** @var DirectorObjectForm $form */
        $db = $form->getDb()->getDbAdapter();
        $roles = Acl::instance()->listRoleNames();
        $select = $db->select()
            ->from('director_datalist_entry', ['entry_name', 'entry_value'])
            ->where('list_id = ?', $this->getSetting('datalist_id'))
            ->order('entry_value ASC');

        if (empty($roles)) {
            $select->where('allowed_roles IS NULL');
        } else {
            $parts = ['allowed_roles IS NULL'];
            foreach ($roles as $role) {
                $parts[] = $db->quoteInto("allowed_roles LIKE ?", '%' . \json_encode($role) . '%');
            }
            $select->where('(' . \implode(' OR ', $parts) . ')');
        }

        return $db->fetchPairs($select);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        /** @var DirectorObjectForm $form */
        $db = $form->getDb();

        $form->addElement('select', 'datalist_id', [
            'label'    => 'List name',
            'required' => true,
            'multiOptions' => $form->optionalEnum($db->enumDatalist()),
        ]);

        $form->addElement('select', 'data_type', [
            'label' => $form->translate('Target data type'),
            'multiOptions' => $form->optionalEnum([
                'string' => $form->translate('String'),
                'array'  => $form->translate('Array'),
            ]),
            'description' => $form->translate(
                'Whether this should be a String or an Array in the generated'
                . ' Icinga configuration. In case you opt for Array, Director'
                . ' users will be able to select multiple elements from the list'
            ),
            'required' => true,
        ]);

        $form->addElement('select', 'behavior', [
            'label'        => $form->translate('Element behavior'),
            'value'        => 'strict',
            'description'  => $form->translate(
                'This allows to show either a drop-down list or an auto-completion'
            ),
            'multiOptions' => [
                'strict' => $form->translate('Dropdown (list values only)'),
                $form->translate('Autocomplete') => [
                    'suggest_strict'   => $form->translate('Strict, list values only'),
                    'suggest_optional' => $form->translate('Allow for values not on the list'),
                    'suggest_extend'   => $form->translate('Extend the list with new values'),
                ]
            ]
        ]);
    }
}
