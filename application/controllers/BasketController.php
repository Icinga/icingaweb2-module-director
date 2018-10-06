<?php

namespace Icinga\Module\Director\Controllers;

use dipl\Html\Link;
use dipl\Web\Widget\NameValueTable;
use Exception;
use Icinga\Module\Director\ConfigDiff;
use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Forms\BasketCreateSnapshotForm;
use Icinga\Module\Director\Forms\BasketForm;
use Icinga\Module\Director\Forms\RestoreBasketForm;
use Icinga\Module\Director\Web\Controller\ActionController;
use dipl\Html\Html;
use Icinga\Module\Director\Web\Table\BasketSnapshotTable;

class BasketController extends ActionController
{
    protected $isApified = true;

    protected function basketTabs()
    {
        $uuid = $this->params->get('uuid');
        return $this->tabs()->add('show', [
            'label' => $this->translate('Basket'),
            'url'   => 'director/basket',
            'urlParams' => ['uuid' => $uuid]
        ])->add('snapshots', [
            'label' => $this->translate('Snapshots'),
            'url' => 'director/basket/snapshots',
            'urlParams' => ['uuid' => $uuid]
        ]);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function indexAction()
    {
        $this->actions()->add(
            Link::create(
                $this->translate('Back'),
                'director/baskets',
                null,
                ['class' => 'icon-left-big']
            )
        );
        $uuid = hex2bin($this->params->get('uuid'));
        $basket = Basket::load($uuid, $this->db());
        $this->basketTabs()->activate('show');
        $this->addTitle($basket->get('basket_name'));
        if ($basket->isEmpty()) {
            $this->content()->add(Html::tag('p', [
                'class' => 'information'
            ], $this->translate('This basket is empty')));
        }
        $this->content()->add(
            (new BasketForm())->setObject($basket)->handleRequest()
        );
    }

    public function createAction()
    {
        $this->actions()->add(
            Link::create(
                $this->translate('back'),
                'director/baskets',
                null,
                ['class' => 'icon-left-big']
            )
        );
        $this->addSingleTab($this->translate('Create Basket'));
        $this->addTitle($this->translate('Create a new Configuration Basket'));
        $form = (new BasketForm())
            ->setDb($this->db())
            ->handleRequest();
        $this->content()->add($form);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function snapshotsAction()
    {
        $uuid = $this->params->get('uuid');
        if ($uuid === null || $uuid === '') {
            $basket = null;
        } else {
            $uuid = hex2bin($uuid);
            $basket = Basket::load($uuid, $this->db());
        }
        if ($basket === null) {
            $this->addTitle($this->translate('Basket Snapshots'));
            $this->addSingleTab($this->translate('Snapshots'));
        } else {
            $this->addTitle(sprintf(
                $this->translate('%: Snapshots'),
                $basket->get('basket_name')
            ));
            $this->basketTabs()->activate('snapshots');
        }
        if ($basket !== null) {
            $this->content()->add(
                (new BasketCreateSnapshotForm())
                    ->setBasket($basket)
                    ->handleRequest()
            );
        }
        $table = new BasketSnapshotTable($this->db());
        if ($basket !== null) {
            $table->setBasket($basket);
        }

        $table->renderTo($this);
    }

    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function snapshotAction()
    {
        $hexUuid = $this->params->getRequired('uuid');
        $binUuid = hex2bin($hexUuid);
        $basket = Basket::load($binUuid, $this->db());
        $snapshot = BasketSnapshot::load([
            'basket_uuid' => $binUuid,
            'ts_create'   => $this->params->getRequired('ts'),
        ], $this->db());

        $this->addTitle(
            $this->translate('%s: %s (Snapshot)'),
            $basket->get('basket_name'),
            substr($hexUuid, 0, 7)
        );

        $this->actions()->add([
            Link::create(
                $this->translate('Show Basket'),
                'director/basket',
                ['uuid' => $hexUuid],
                ['data-base-target' => '_next']
            ),
            Link::create(
                $this->translate('Restore'),
                $this->url()->with('action', 'restore'),
                null,
                ['class' => 'icon-rewind']
            )
        ]   );

        if ($this->params->get('action') === 'restore') {
            $form = new RestoreBasketForm();
            $form
                ->setSnapshot($snapshot)
                ->handleRequest();
            $this->content()->add($form);
            $targetDbName = $form->getValue('target_db');
            $connection = $form->getDb();
        } else {
            $targetDbName = null;
            $connection = $this->db();
        }

        $json = $snapshot->getJsonDump();
        $this->addSingleTab($this->translate('Snapshot'));
        $all = Json::decode($json);
        foreach ($all as $type => $objects) {
            $table = new NameValueTable();
            $table->setAttribute('data-base-target', '_next');
            foreach ($objects as $key => $object) {
                $linkParams = [
                    'uuid'     => $hexUuid,
                    'checksum' => $this->params->get('checksum'),
                    'ts'       => $this->params->get('ts'),
                    'type'     => $type,
                    'key'      => $key,
                ];
                if ($targetDbName !== null) {
                    $linkParams['target_db'] = $targetDbName;
                }
                try {
                    $current = BasketSnapshot::instanceByIdentifier($type, $key, $connection);
                    if ($current === null) {
                        $table->addNameValueRow(
                            $key,
                                Link::create(
                                Html::tag('strong', ['style' => 'color: green'], $this->translate('new')),
                                'director/basket/snapshotobject',
                                $linkParams
                            )
                        );
                        continue;
                    }
                    $hasChanged = Json::encode($current->export()) !== Json::encode($object);
                    $table->addNameValueRow(
                        $key,
                        $hasChanged
                        ? Link::create(
                            Html::tag('strong', ['style' => 'color: orange'], $this->translate('modified')),
                            'director/basket/snapshotobject',
                            $linkParams
                        )
                        : Html::tag('span', ['style' => 'color: green'], $this->translate('unchanged'))
                    );
                } catch (Exception $e) {
                    $table->addNameValueRow(
                        $key,
                        $e->getMessage()
                    );
                }
            }
            $this->content()->add(Html::tag('h2', $type));
            $this->content()->add($table);
        }
    }

    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function snapshotobjectAction()
    {
        $hexUuid = $this->params->getRequired('uuid');
        $binUuid = hex2bin($hexUuid);
        $snapshot = BasketSnapshot::load([
            'basket_uuid' => $binUuid,
            'ts_create'   => $this->params->getRequired('ts'),
        ], $this->db());
        $snapshotUrl = $this->url()->without('type')->without('key')->setPath('director/basket/snapshot');
        $type = $this->params->get('type');
        $key = $this->params->get('key');

        $this->addTitle($this->translate('Single Object Diff'));
        $this->content()->add(Html::tag('p', [
            'class' => 'information'
        ], Html::sprintf(
            $this->translate('Comparing %s "%s" from Snapshot "%s" to current config'),
            $type,
            $key,
            Link::create(
                substr($hexUuid, 0, 7),
                $snapshotUrl,
                null,
                ['data-base-target' => '_next']
            )
        )));
        $this->actions()->add([
            Link::create(
                $this->translate('back'),
                $snapshotUrl,
                null,
                ['class' => 'icon-left-big']
            ),
            Link::create(
                $this->translate('Restore'),
                $this->url()->with('action', 'restore'),
                null,
                ['class' => 'icon-rewind']
            )
        ]);

        $json = $snapshot->getJsonDump();
        $this->addSingleTab($this->translate('Snapshot'));
        $objects = Json::decode($json);
        $targetDbName = $this->params->get('target_db');
        if ($targetDbName === null) {
            $connection = $this->db();
        } else {
            $connection = Db::fromResourceName($targetDbName);
        }
        $object = $objects->$type->$key;
        $current = BasketSnapshot::instanceByIdentifier($type, $key, $connection);
        $this->content()->add(
            ConfigDiff::create(
                Json::encode($object, JSON_PRETTY_PRINT),
                Json::encode($current->export(), JSON_PRETTY_PRINT)
            )->setHtmlRenderer('Inline')
        );
    }
}
