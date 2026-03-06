<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\CustomVariableForm;
use Icinga\Module\Director\Web\Widget\CustomVarFieldsTable;
use Icinga\Web\Notification;
use ipl\Html\Html;
use ipl\Html\Text;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;

class VariablesController extends CompatController
{
    public function indexAction()
    {
        $this->addTitleTab($this->translate('Custom Variables'));

        $db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        )->getDbAdapter();

        $query = $db->select()
            ->from(['dp' => 'director_property'], [])
            ->joinLeft(['ihp' => 'icinga_host_property'], 'ihp.property_uuid = dp.uuid', [])
            ->columns([
                'key_name',
                'uuid',
                'parent_uuid',
                'value_type',
                'label',
                'description',
                'used_count' => 'COUNT(ihp.property_uuid)'
            ])
            ->where('parent_uuid IS NULL')
            ->group('dp.uuid')
            ->order('key_name');

        $properties = new CustomVarFieldsTable($db->fetchAll($query));

        $this->addControl(Html::tag('div', ['class' => 'custom-variable-form'], [
            (new ButtonLink(
                [Text::create($this->translate('Create Custom Variable'))],
                Url::fromPath('director/variables/add'),
                null,
                [
                    'class' => 'control-button'
                ]
            ))->setBaseTarget('_next')
        ]));

        $this->addContent($properties);
    }

    public function addAction()
    {
        $this->addTitleTab($this->translate('Create Custom Variable'));
        $db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );

        $propertyForm = (new CustomVariableForm($db))
            ->on(CustomVariableForm::ON_SUCCESS, function (CustomVariableForm $form) {
                Notification::success(sprintf(
                    $this->translate('Property "%s" has successfully been added'),
                    $form->getValue('key_name')
                ));

                $this->redirectNow(Url::fromPath('director/customvar', ['uuid' => $form->getUUid()->toString()]));
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($propertyForm);
    }
}
