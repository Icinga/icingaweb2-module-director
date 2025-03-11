<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\PropertyForm;
use Icinga\Module\Director\Web\Widget\PropertyTable;
use Icinga\Web\Notification;
use ipl\Html\Html;
use ipl\Html\Text;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;

class PropertiesController extends CompatController
{
    public function indexAction()
    {
        $this->addTitleTab($this->translate('Properties'));

        $db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        )->getDbAdapter();

        $query = $db->select()
            ->from('director_property')
            ->where('parent_uuid IS NULL')
            ->order('key_name');

        $properties = new PropertyTable($db->fetchAll($query));

        $this->addControl(Html::tag('div', ['class' => 'property-form'], [
            (new ButtonLink(
                [Text::create('Add property')],
                Url::fromPath('director/properties/add'),
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
        $this->addTitleTab($this->translate('Add property'));
        $db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );

        $propertyForm = (new PropertyForm($db))
            ->on(PropertyForm::ON_SUCCESS, function (PropertyForm $form) {
                Notification::success(sprintf(
                    $this->translate('Property "%s" has successfully been added'),
                    $form->getValue('key_name')
                ));

                $this->redirectNow(Url::fromPath('director/property', ['uuid' => $form->getUUid()->toString()]));
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($propertyForm);
    }
}
