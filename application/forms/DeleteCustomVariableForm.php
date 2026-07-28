<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\CustomVariable\CustomVariableValueCleaner;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Web\Widget\CustomVarObjectList;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\ListItem;
use Ramsey\Uuid\Uuid;
use Zend_Db_Expr;

class DeleteCustomVariableForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    /** @var bool Whether to hide the key name element or not (checked for the fixed array) */
    private $hideKeyNameElement = false;

    /** @var bool Whether the field is a nested field or not */
    private $isNestedField = false;

    /** @var CustomVariableValueCleaner */
    protected $cleaner;

    public function __construct(
        protected Db $db,
        protected array $property,
        protected array $parent = []
    ) {
        $this->cleaner = new CustomVariableValueCleaner($db);
    }

    /**
     * Fetch the give custom variable usage in templates
     *
     * @return array
     */
    private function fetchCustomVarUsage(): array
    {
        $db = $this->db->getDbAdapter();
        if ($this->parent) {
            if ($this->parent['parent_uuid'] !== null) {
                $uuid = $this->parent['parent_uuid'];
            } else {
                $uuid = $this->parent['uuid'];
            }
        } else {
            $uuid = $this->property['uuid'];
        }

        $uuid = DbUtil::quoteBinaryCompat($uuid, $db);

        $objectClasses = ['host', 'service', 'notification', 'command', 'user'];
        $usage = [];

        foreach ($objectClasses as $objectClass) {
            $customPropertyQuery = $db
                ->select()
                ->from(['io' => "icinga_$objectClass"], [])
                ->join(['iov' => "icinga_$objectClass" . '_var'], "io.id = iov.$objectClass" . '_id', [])
                ->join(['dp' => 'director_property'], 'iov.property_uuid = dp.uuid', []);

            $unionQuery = $db
                ->select()
                ->from(['io' => "icinga_$objectClass"], [])
                ->join(['iop' => "icinga_$objectClass" . '_property'], "iop.$objectClass" . '_uuid = io.uuid', [])
                ->join(['dp' => 'director_property'], 'iop.property_uuid = dp.uuid', []);

            $columns = [
                'name' => 'io.object_name',
                'object_class' => new Zend_Db_Expr("'$objectClass'"),
                'type' => 'io.object_type'
            ];

            if ($objectClass === 'service') {
                $customPropertyQuery = $customPropertyQuery
                    ->joinLeft(['ioh' => 'icinga_host'], 'io.host_id = ioh.id', []);
                $unionQuery = $unionQuery->joinLeft(['ioh' => 'icinga_host'], 'io.host_id = ioh.id', []);
                $columns['host_name'] = 'ioh.object_name';
            }

            $customPropertyQuery = $customPropertyQuery->columns($columns)
                                                       ->where('dp.uuid = ?', $uuid);

            $unionQuery = $unionQuery->columns($columns)
                                     ->where('dp.uuid = ?', $uuid);

            $usage[] = $db->fetchAll($db->select()->union([$customPropertyQuery, $unionQuery]));
        }

        return array_merge(...$usage);
    }

    protected function assemble(): void
    {
        $customVarUsage = $this->fetchCustomVarUsage();
        if (count($customVarUsage) > 0) {
            if ($this->parent) {
                if ($this->parent['parent_uuid'] !== null) {
                    $info = sprintf(
                        $this->translate(
                            'Deleting this sub field from custom variable "%s" will remove this field in'
                            . ' the corresponding custom variables from the below templates and objects.'
                            . ' Are you sure you want to delete it?'
                        ),
                        $this->cleaner->fetchProperty(Uuid::fromBytes($this->parent['parent_uuid']))['key_name']
                    );
                } else {
                    $info = sprintf($this->translate(
                        'Deleting this field from custom variable "%s" will remove this field in'
                        . ' the corresponding custom variable from the below templates and objects.'
                        . ' Are you sure you want to delete it?'
                    ), $this->parent['key_name']);
                }
            } else {
                $info = $this->translate(
                    'Deleting this custom variable will remove it from the below templates and'
                    . ' objects. Are you sure you want to delete it?'
                );
            }
        } else {
            if ($this->parent) {
                $info = $this->translate('The field is not in use and hence can be safely deleted.');
            } else {
                $info = $this->translate('The custom variable is not in use and hence can be safely deleted.');
            }
        }

        $this->addHtml(new HtmlElement(
            'div',
            Attributes::create(['class' => 'form-description']),
            new Icon('info-circle', ['class' => 'form-description-icon']),
            new HtmlElement(
                'ul',
                null,
                new HtmlElement('li', null, Text::create($info))
            )
        ));

        $objectClass = null;
        $usageList = (new CustomVarObjectList($customVarUsage));
        $usageList->on(
            CustomVarObjectList::BEFORE_ITEM_ADD,
            function (ListItem $item, $data) use (&$objectClass, $usageList) {
                if ($objectClass !== $data->object_class) {
                    $usageList->addHtml(
                        HtmlElement::create(
                            'li',
                            ['class' => 'list-item'],
                            HtmlElement::create('h2', content: ucfirst($data->object_class) . 's')
                        )
                    );
                    $objectClass = $data->object_class;
                }
            }
        );

        $this->addHtml($usageList);

        $this->addCsrfCounterMeasure(Session::getSession()->getId());
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Delete'),
            'class' => 'btn-remove'
        ]);
    }

    protected function onSuccess(): void
    {
        $uuid = $this->property['uuid'];
        $db = $this->db;
        $prop = $this->property;
        $cleaner = $this->cleaner;

        // A dictionary can be nested arbitrarily deep (dictionary -> dictionary -> ...),
        // and any level of that nesting might itself be a datalist-backed field. Hence,
        // any links between datalists and children or grandchildren in the hierarchy must
        // also be removed.
        $allUuids = array_merge([$uuid], $this->collectDescendantUuids($uuid));
        $quotedAllUuids = DbUtil::quoteBinaryCompat($allUuids, $this->db->getDbAdapter());

        $keptValues = 0;
        $db->runFailSafeTransaction(function () use ($db, $prop, $quotedAllUuids, $cleaner, &$keptValues) {
            $db->delete('director_property_datalist', Filter::where('property_uuid', $quotedAllUuids));

            $cleaner->removeObjectCustomVars($prop, $this->parent);
            $cleaner->removeFromOverrideServiceVars($prop, $this->parent);

            if (empty($this->parent)) {
                $keptValues = $cleaner->deleteStoredValues($prop['key_name']);
            }

            $db->delete('director_property', Filter::where('uuid', $quotedAllUuids));
        });

        if ($keptValues > 0) {
            Notification::warning(sprintf(
                $this->translate(
                    'Kept %d stored value(s) for "%s", a Data Field with the same name still exists. '
                    . 'Rename or remove it, then delete this property again to clear them.'
                ),
                $keptValues,
                $prop['key_name']
            ));
        }
    }

    /**
     * Recursively collect the raw binary UUIDs of all descendants (children and
     * grandchildren) of the property with the given raw binary UUID.
     *
     * @param string $uuid Raw binary UUID of the property to start from
     *
     * @return string[] Raw binary UUIDs of all descendants, not including $uuid itself
     */
    private function collectDescendantUuids(string $uuid): array
    {
        $dba = $this->db->getDbAdapter();
        $descendants = [];
        $parents = [$uuid];

        while (! empty($parents)) {
            $children = $dba->fetchCol(
                $dba->select()
                    ->from('director_property', ['uuid'])
                    ->where('parent_uuid IN (?)', DbUtil::quoteBinaryCompat($parents, $dba))
            );
            $children = array_map([DbUtil::class, 'binaryResult'], $children);

            $descendants[] = $children;
            $parents = $children;
        }

        return array_merge(...$descendants);
    }
}
