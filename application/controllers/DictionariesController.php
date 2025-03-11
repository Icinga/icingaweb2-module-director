<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\DictionaryForm;
use Icinga\Module\Director\Web\Widget\DictionaryTable;
use Icinga\Web\Notification;
use ipl\Html\Html;
use ipl\Html\Text;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;

class DictionariesController extends CompatController
{
    public function indexAction()
    {
        $this->addTitleTab($this->translate('Dictionaries'));

        $db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );

        $query = $db->select()->from('director_dictionary', ['*']);

        $dictionaries = new DictionaryTable($db->fetchAll($query));

        $this->addControl(Html::tag('div', ['class' => 'dictionary-form'], [
            (new ButtonLink(
                [Text::create('Add Dictionary')],
                Url::fromPath('director/dictionaries/add'),
                null,
                [
                    'class' => 'control-button'
                ]
            ))->setBaseTarget('_next')
        ]));

        $this->addContent($dictionaries);
    }

    public function addAction()
    {
        $this->addTitleTab($this->translate('Add Dictionary'));
        $db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );

        $dictionaryForm = (new DictionaryForm($db))
            ->on(DictionaryForm::ON_SUCCESS, function (DictionaryForm $form) {
                Notification::success(sprintf(
                    $this->translate('Channel "%s" has successfully been saved'),
                    $form->getValue('key')
                ));

                $this->redirectNow(Url::fromPath('director/dictionary'));
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($dictionaryForm);
    }
}