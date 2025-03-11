<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\PropertyForm;
use Icinga\Module\Director\Web\Widget\PropertyTable;
use Icinga\Web\Notification;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db;

class PropertyController extends CompatController
{
    /** @var Db */
    protected $db;

    public function init()
    {
        parent::init();

        $this->db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );
    }

    public function indexAction()
    {
        $uuid = $this->params->shiftRequired('uuid');
        $uuid = Uuid::fromString($uuid);
        $db = $this->db->getDbAdapter();
        $property = $this->fetchProperty($uuid);

        if ($property['value_type'] === 'array' && $property['instantiable'] === 'y') {
            $itemTypeQuery = $db
                ->select()->from('director_property', 'value_type')
                ->where(
                    'parent_uuid = ? AND key_name = \'0\'',
                    $uuid->getBytes()
                );

            $property['item_type'] = $db->fetchOne($itemTypeQuery);
        }

        $showFields = ($property['value_type'] === 'array' && $property['instantiable'] !== 'y')
            || $property['value_type'] === 'dict';
        $propertyForm = (new PropertyForm($this->db, $uuid))
            ->populate($property)
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(PropertyForm::ON_SENT, function (PropertyForm $form) use (&$showFields) {
                $values = $form->getValues();
                $showFields = ($values['value_type'] === 'array' && $values['instantiable'] !== 'y')
                    || $values['value_type'] === 'dict';
            })
            ->on(PropertyForm::ON_SUCCESS, function (PropertyForm $form) {
                if ($form->getPressedSubmitElement()->getName() === 'delete') {
                    Notification::success(sprintf(
                        $this->translate('Property "%s" has successfully been deleted'),
                        $form->getValue('key_name')
                    ));

                    $this->redirectNow('__CLOSE__');
                } else {
                    Notification::success(sprintf(
                        $this->translate('Property "%s" has successfully been saved'),
                        $form->getValue('key_name')
                    ));

                    $this->sendExtraUpdates(['#col1']);

                    $this->redirectNow(
                        Url::fromPath('director/property', ['uuid' => $form->getUUid()->toString()])
                    );
                }
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($propertyForm);

        if ($showFields) {
            $this->addContent(new HtmlElement('h2', null, Text::create($this->translate('Fields'))));
            $button = (new ButtonLink(
                Text::create($this->translate('Add Field')),
                Url::fromPath('director/property/add-field', [
                    'uuid' => $uuid->toString()
                ]),
                null,
                ['class' => 'control-button']
            ))->openInModal();

            $fieldQuery = $db
                ->select()
                ->from('director_property')
                ->where('parent_uuid = ?', $uuid->getBytes())
                ->order('key_name');

            $this->addContent($button);

            $fields = new PropertyTable($db->fetchAll($fieldQuery), true);
            $this->addContent($fields);
        }

        $this->addTitleTab($this->translate('Property') . ': ' . $property['key_name']);
    }

    public function addFieldAction()
    {
        $uuid = $this->params->shiftRequired('uuid');
        $this->addTitleTab($this->translate('Add Field'));
        $uuid = Uuid::fromString($uuid);

        $parent = $this->fetchProperty($uuid);
        $hideKeyNameField = $parent['value_type'] === 'array'
            && $parent['instantiable'] === 'n';

        $propertyForm = (new PropertyForm($this->db, null, true, $uuid))
            ->setHideKeyNameElement($hideKeyNameField)
            ->setIsNestedField($parent['parent_uuid'] !== null)
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(PropertyForm::ON_SUCCESS, function (PropertyForm $form) {
                Notification::success(sprintf(
                    $this->translate('Property "%s" has successfully been saved'),
                    $form->getValue('key_name')
                ));

                $this->sendExtraUpdates(['#col1']);
                $this->redirectNow(
                    Url::fromPath('director/property', ['uuid' => $form->getParentUUid()->toString()])
                );
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($propertyForm);
    }

    public function editFieldAction()
    {
        $uuid = Uuid::fromString($this->params->shiftRequired('uuid'));
        $parentUuid = Uuid::fromString($this->params->shiftRequired('parent_uuid'));

        $parent = $this->fetchProperty($parentUuid);
        $hideKeyNameField = $parent['value_type'] === 'array'
            && $parent['instantiable'] === 'n';

        $property = $this->fetchProperty($uuid);
        $db = $this->db->getDbAdapter();

        if ($property['value_type'] === 'array' && $property['instantiable'] === 'y') {
            $itemTypeQuery = $db
                ->select()->from('director_property', 'value_type')
                ->where(
                    'parent_uuid = ? AND key_name = \'0\'',
                    $uuid->getBytes()
                );

            $property['item_type'] = $db->fetchOne($itemTypeQuery);
        }

        $this->addTitleTab(sprintf($this->translate('Edit Field: %s'), $property['key_name']));

        $showFields = ($property['value_type'] === 'array' && $property['instantiable'] !== 'y')
            || $property['value_type'] === 'dict';
        $propertyForm = (new PropertyForm($this->db, $uuid, true, $parentUuid))
            ->setHideKeyNameElement($hideKeyNameField)
            ->setIsNestedField($parent['parent_uuid'] !== null)
            ->populate($property)
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(PropertyForm::ON_SENT, function (PropertyForm $form) use (&$showFields) {
                $values = $form->getValues();
                $showFields = ($values['value_type'] === 'array' && $values['instantiable'] !== 'y')
                    || $values['value_type'] === 'dict';
            })
            ->on(PropertyForm::ON_SUCCESS, function (PropertyForm $form) {
                if ($form->getPressedSubmitElement()->getName() === 'delete') {
                    Notification::success(sprintf(
                        $this->translate('Property "%s" has successfully been deleted'),
                        $form->getValue('key_name')
                    ));

                    $this->redirectNow('__CLOSE__');
                } else {
                    Notification::success(sprintf(
                        $this->translate('Property "%s" has successfully been saved'),
                        $form->getValue('key_name')
                    ));

                    $this->redirectNow(Url::fromRequest()->getAbsoluteUrl());
                }
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($propertyForm);

        if ($showFields) {
            $this->addContent(new HtmlElement('h2', null, Text::create($this->translate('Fields'))));
            $button = (new ButtonLink(
                Text::create($this->translate('Add Field')),
                Url::fromPath('director/property/add-field', [
                    'uuid' => $uuid->toString()
                ]),
                null,
                ['class' => 'control-button']
            ))->openInModal();

            $fieldQuery = $db
                ->select()
                ->from('director_property')
                ->where('parent_uuid = ?', $uuid->getBytes())
                ->order('key_name');

            $this->addContent($button);

            $fields = new PropertyTable($db->fetchAll($fieldQuery), true);
            $this->addContent($fields);
        }
    }

    /**
     * Fetch property for the given UUID
     *
     * @param UuidInterface $uuid UUID of the given property
     *
     * @return array<string, mixed>
     */
    private function fetchProperty(UuidInterface $uuid): array
    {
        $db = $this->db->getDbAdapter();

        $query = $db
            ->select()->from('director_property')
            ->where('uuid = ?', $uuid->getBytes());

        return $db->fetchRow($query, [], Zend_Db::FETCH_ASSOC);
    }
}
