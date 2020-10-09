<?php

namespace Icinga\Module\Director\Import;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\ImportSourceForm;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Form\QuickForm;

class ImportSourceDirectorObject extends ImportSourceHook
{
    protected $db;

    public function getName()
    {
        return 'Director Objects';
    }

    public static function getDefaultKeyColumnName()
    {
        return 'object_name';
    }

    public function fetchData()
    {
        $db = $this->db();
        $objectClass = $this->getSetting('object_class');
        $objectType = $this->getSetting('object_type');
        /** @var IcingaObject $class fake type hint, it's a string */
        $class = IcingaObject::classByType($objectClass);
        if ($objectType) {
            $dummy = $class::create();
            $query = $db->getDbAdapter()->select()
                ->from($dummy->getTableName())
                ->where('object_type = ?', $objectType);
        } else {
            $query = null;
        }
        $result = [];
        $resolved = $this->getSetting('resolved') === 'y';
        foreach ($class::loadAllByType($objectClass, $db, $query) as $object) {
            $result[] = $object->toPlainObject($resolved);
        }
        if ($objectClass === 'zone') {
            $this->enrichZonesWithDeploymentZone($result);
        }
        return $result;
    }

    protected function enrichZonesWithDeploymentZone(&$zones)
    {
        $masterZone = $this->db()->getMasterZoneName();
        foreach ($zones as $zone) {
            $zone->is_master_zone = $zone->object_name === $masterZone;
        }
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        /** @var ImportSourceForm $form */
        Util::addDbResourceFormElement($form, 'resource');
        $form->getElement('resource')
            ->setValue(Config::module('director')->get('db', 'resource'));
        $form->addElement('select', 'object_class', [
            'label'    => $form->translate('Director Object'),
            'multiOptions' => [
                'host'     => $form->translate('Host'),
                'endpoint' => $form->translate('Endpoint'),
                'zone'     => $form->translate('Zone'),
            ],
            'required' => true,
        ]);
        $form->addElement('select', 'object_type', [
            'label'    => $form->translate('Object Type'),
            'multiOptions' => [
                null              => $form->translate('All Object Types'),
                'object'          => $form->translate('Objects'),
                'template'        => $form->translate('Templates'),
                'external_object' => $form->translate('External Objects'),
                'apply'           => $form->translate('Apply Rules'),
            ],
        ]);

        /** @var $form \Icinga\Module\Director\Web\Form\DirectorObjectForm */
        $form->addBoolean('resolved', [
            'label' => $form->translate('Resolved'),
        ], 'n');

        return $form;
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = Db::fromResourceName($this->settings['resource']);
        }

        return $this->db;
    }

    public function listColumns()
    {
        $rows = $this->fetchData();
        $columns = [];

        foreach ($rows as $object) {
            foreach (array_keys((array) $object) as $column) {
                if (! isset($columns[$column])) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }
}
