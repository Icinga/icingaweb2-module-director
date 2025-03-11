<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\DictionaryForm;
use Icinga\Module\Director\Web\Widget\DictionaryTable;
use Icinga\Web\Notification;
use ipl\Html\Text;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;
use Ramsey\Uuid\Uuid;

class DictionaryController extends CompatController
{
    public function indexAction()
    {
        $this->addTitleTab($this->translate('Dictionary'));
        $uuid = $this->params->shiftRequired('uuid');
        $db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );

        $query = $db->select()->from('director_dictionary', ['*'])
            ->where('uuid', $uuid);

        $dictionary = [$db->fetchRow($query)];

        $this->addContent(new DictionaryTable($dictionary, false));
        $button = (new ButtonLink(
            Text::create($this->translate('Add Field')),
            Url::fromPath('director/dictionary/field', [
                'uuid' => $uuid
            ]),
            null,
            ['class' => 'control-button']
        ))->openInModal();

        $this->addControl($button);
    }

    public function fieldAction()
    {
        $uuid = $this->params->shiftRequired('uuid');
        $this->addTitleTab($this->translate('Add Field'));
        $db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );

        $dictionaryForm = (new DictionaryForm($db, null, true, Uuid::fromString($uuid)))
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(DictionaryForm::ON_SUCCESS, function (DictionaryForm $form) {
                Notification::success(sprintf(
                    $this->translate('Channel "%s" has successfully been saved'),
                    $form->getValue('key')
                ));

                $this->redirectNow(Url::fromRequest()->getAbsoluteUrl());
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($dictionaryForm);
    }
}