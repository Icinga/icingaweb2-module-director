<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use gipfl\Diff\HtmlRenderer\InlineDiff;
use gipfl\Diff\PhpDiff;
use gipfl\IcingaWeb2\Link;
use gipfl\Web\Table\NameValueTable;
use gipfl\Web\Widget\Hint;
use Icinga\Date\DateFormatter;
use Icinga\Module\Director\CustomVariable\PropertyValueChange;
use Icinga\Module\Director\CustomVariable\PropertyValueMigration;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use Icinga\Module\Director\DirectorObject\Automation\BasketDiff;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Forms\AddToBasketForm;
use Icinga\Module\Director\Forms\BasketCreateSnapshotForm;
use Icinga\Module\Director\Forms\BasketForm;
use Icinga\Module\Director\Forms\BasketUploadForm;
use Icinga\Module\Director\Forms\RestoreBasketForm;
use Icinga\Module\Director\Web\Controller\ActionController;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use Icinga\Module\Director\Web\Table\BasketSnapshotTable;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlString;
use ipl\Web\Common\CalloutType;
use ipl\Web\Widget\Callout;
use Ramsey\Uuid\Uuid;

class BasketController extends ActionController
{
    protected $isApified = true;

    protected function basketTabs()
    {
        $name = $this->params->get('name');
        return $this->tabs()->add('show', [
            'label' => $this->translate('Basket'),
            'url'   => 'director/basket',
            'urlParams' => ['name' => $name]
        ])->add('snapshots', [
            'label' => $this->translate('Snapshots'),
            'url' => 'director/basket/snapshots',
            'urlParams' => ['name' => $name]
        ]);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Exception\MissingParameterException
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
        $basket = $this->requireBasket();
        $this->basketTabs()->activate('show');
        $this->addTitle($basket->get('basket_name'));
        if ($basket->isEmpty()) {
            $this->content()->add(Hint::info($this->translate('This basket is empty')));
        }
        $this->content()->add(
            (new BasketForm())->setObject($basket)->handleRequest()
        );
    }

    /**
     * @throws \Icinga\Exception\MissingParameterException
     */
    public function addAction()
    {
        $this->actions()->add(
            Link::create(
                $this->translate('Baskets'),
                'director/baskets',
                null,
                ['class' => 'icon-tag']
            )
        );
        $this->addSingleTab($this->translate('Add to Basket'));
        $this->addTitle($this->translate('Add chosen objects to a Configuration Basket'));
        $form = new AddToBasketForm();
        $form->setDb($this->db())
            ->setType($this->params->getRequired('type'))
            ->setNames($this->url()->getParams()->getValues('names'))
            ->handleRequest();
        $this->content()->add($form);
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

    public function uploadAction()
    {
        $this->actions()->add(
            Link::create(
                $this->translate('back'),
                'director/baskets',
                null,
                ['class' => 'icon-left-big']
            )
        );
        $this->addSingleTab($this->translate('Upload a Basket'));
        $this->addTitle($this->translate('Upload a Configuration Basket'));
        $form = (new BasketUploadForm())
            ->setDb($this->db())
            ->handleRequest();
        $this->content()->add($form);
    }

    public function uploadSnapshotAction()
    {
        $basket = Basket::load($this->params->get('name'), $this->db());
        $this->actions()->add(
            Link::create(
                $this->translate('back'),
                'director/basket/snapshots',
                ['name' => $basket->get('basket_name')],
                ['class' => 'icon-left-big']
            )
        );
        $this->basketTabs()->activate('snapshots');
        $this->addTitle($this->translate('Upload a Configuration Basket Snapshot'));
        $form = (new BasketUploadForm())
            ->setObject($basket)
            ->setDb($this->db())
            ->handleRequest();
        $this->content()->add($form);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function snapshotsAction()
    {
        $name = $this->params->get('name');
        if ($name === null || $name === '') {
            $basket = null;
        } else {
            $basket = Basket::load($name, $this->db());
        }
        if ($basket === null) {
            $this->addTitle($this->translate('Basket Snapshots'));
            $this->addSingleTab($this->translate('Snapshots'));
        } else {
            $this->addTitle(sprintf(
                $this->translate('%s: Snapshots'),
                $basket->get('basket_name')
            ));
            $this->basketTabs()->activate('snapshots');
            $this->actions()->add(Link::create(
                $this->translate('Upload'),
                'director/basket/upload-snapshot',
                ['name' => $basket->get('basket_name')],
                ['class' => 'icon-upload']
            ));
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
        $basket = $this->requireBasket();
        $snapshot = BasketSnapshot::load([
            'basket_uuid' => $basket->get('uuid'),
            'ts_create'   => $this->params->getRequired('ts'),
        ], $this->db());
        $snapSum = bin2hex($snapshot->get('content_checksum'));

        if ($this->params->get('action') === 'download') {
            $this->getResponse()->setHeader('Content-Type', 'application/json', true);
            $this->getResponse()->setHeader('Content-Disposition', sprintf(
                'attachment; filename=Director-Basket_%s_%s.json',
                str_replace([' ', '"'], ['_', '_'], iconv(
                    'UTF-8',
                    'ISO-8859-1//IGNORE',
                    $basket->get('basket_name')
                )),
                substr($snapSum, 0, 7)
            ));
            echo $snapshot->getJsonDump();
            return;
        }

        $this->addTitle(
            $this->translate('%s: %s (Snapshot)'),
            $basket->get('basket_name'),
            substr($snapSum, 0, 7)
        );

        $this->actions()->add([
            Link::create(
                $this->translate('Show Basket'),
                'director/basket',
                ['name' => $basket->get('basket_name')],
                ['data-base-target' => '_next']
            ),
            Link::create(
                $this->translate('Restore'),
                $this->url()->with('action', 'restore'),
                null,
                ['class' => 'icon-rewind']
            ),
            Link::create(
                $this->translate('Download'),
                $this->url()
                    ->with([
                        'action' => 'download',
                        'dbResourceName' => $this->getDbResourceName()
                    ]),
                null,
                [
                    'class'  => 'icon-download',
                    'target' => '_blank'
                ]
            ),
        ]);

        $properties = new NameValueTable();
        $properties->addNameValuePairs([
            $this->translate('Created') => DateFormatter::formatDateTime($snapshot->get('ts_create') / 1000),
            $this->translate('Content Checksum') => bin2hex($snapshot->get('content_checksum')),
        ]);
        $this->content()->add($properties);

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

        $this->addSingleTab($this->translate('Snapshot'));
        $diff = new BasketDiff($snapshot, $connection);
        foreach ($diff->getBasketObjects() as $type => $objects) {
            if ($type === 'Datafield') {
                // TODO: we should now be able to show all fields and link
                //       to a "diff" for the ones that should be created
                // $this->content()->add(Html::tag('h2', sprintf('+%d Datafield(s)', count($objects))));
                continue;
            }
            $table = new NameValueTable();
            $table->addAttributes([
                'class' => ['table-basket-changes', 'table-row-selectable'],
                'data-base-target' => '_next',
            ]);
            $blockedMigrations = [];
            foreach ($objects as $key => $object) {
                // A custom variable's row is keyed by its uuid, not a name.
                // Show its real name instead so the list reads like the others.
                $rowLabel = $type === 'CustomVariable' ? ($object->key_name ?? $key) : $key;
                $linkParams = [
                    'name'     => $basket->get('basket_name'),
                    'checksum' => $this->params->get('checksum'),
                    'ts'       => $this->params->get('ts'),
                    'type'     => $type,
                    'key'      => $key,
                ];
                if ($targetDbName !== null) {
                    $linkParams['target_db'] = $targetDbName;
                }
                try {
                    if ($uuid = $object->uuid ?? null) {
                        $uuid = Uuid::fromString($uuid);
                    }
                    if ($diff->hasCurrentInstance($type, $key, $uuid)) {
                        if ($diff->hasChangedFor($type, $key, $uuid)) {
                            $link = Link::create(
                                $this->translate('modified'),
                                'director/basket/snapshotobject',
                                $linkParams,
                                ['class' => 'basket-modified']
                            );
                        } else {
                            $link = Html::tag(
                                'span',
                                ['class' => 'basket-unchanged'],
                                $this->translate('unchanged')
                            );
                        }
                    } else {
                        $link = Link::create(
                            $this->translate('new'),
                            'director/basket/snapshotobject',
                            $linkParams,
                            ['class' => 'basket-new']
                        );
                    }

                    $value = [$link];
                    if ($type === 'CustomVariable') {
                        $migration = $diff->getCustomPropertyMigrationPreview($key);
                        if ($migration->blocked) {
                            // Collected together and shown once below the header instead of
                            // repeating the same warning on every blocked row.
                            $blockedMigrations[$rowLabel] = $migration;
                        } else {
                            $migrationHint = $this->describeCustomPropertyMigrationChanges($migration, $diff);
                            if ($migrationHint !== null) {
                                $value[] = $migrationHint;
                            }
                        }
                    }

                    $table->addNameValueRow($rowLabel, $value);
                } catch (Exception $e) {
                    $table->addNameValueRow(
                        $rowLabel,
                        Html::tag('a', sprintf(
                            '%s (%s:%d)',
                            $e->getMessage(),
                            basename($e->getFile()),
                            $e->getLine()
                        ))
                    );
                }
            }
            $this->content()->add(Html::tag('h2', $type));
            if (! empty($blockedMigrations)) {
                $this->content()->add($this->renderBlockedCustomPropertiesWarning($blockedMigrations, $diff));
            }
            $this->content()->add($table);
        }
    }

    /**
     * Shows every custom variable a restore can't rename or retype because a
     * Data Field already owns that name
     *
     * @param PropertyValueMigration[] $blockedMigrations keyed by the property's own name
     *
     * @return BaseHtmlElement
     */
    private function renderBlockedCustomPropertiesWarning(array $blockedMigrations, BasketDiff $diff): BaseHtmlElement
    {
        $items = [];
        $total = 0;
        foreach ($blockedMigrations as $rowLabel => $migration) {
            $affected = $diff->countStoredCustomVariableValues($migration->oldVarname);
            $total += $affected;

            $items[] = Html::tag('li', sprintf(
                $this->translate('%s: %s, %d stored value(s) stay as they are'),
                $rowLabel,
                $this->describeBlockedCustomPropertyReason($migration),
                $affected
            ));
        }

        // The restore notification adds every blocked property's count together into
        // one number, spell that total out here too so both numbers can be checked
        // against each other, a single property's own count on its own line above
        // won't match it once more than one property is blocked.
        return new Callout(
            CalloutType::Warning,
            (new HtmlDocument())->addHtml(
                Html::tag(
                    'p',
                    sprintf(
                        $this->translate(
                            'These custom variables are blocked by a legacy Data Field, their stored '
                            . 'values stay under the current name and type, %d in total:'
                        ),
                        $total
                    )
                ),
                Html::tag('ul', ['class' => 'basket-blocked-properties'], $items)
            )
        );
    }

    /**
     * A Data Field can own either the old or the new name. Say what the rename
     * would have been instead of guessing which one it collided with
     */
    private function describeBlockedCustomPropertyReason(PropertyValueMigration $migration): string
    {
        if ($migration->oldVarname !== $migration->newVarname) {
            return sprintf(
                $this->translate('renaming "%s" to "%s" collides with an existing Data Field'),
                $migration->oldVarname,
                $migration->newVarname
            );
        }

        return sprintf(
            $this->translate('a Data Field named "%s" already exists'),
            $migration->oldVarname
        );
    }

    /**
     * Describe what restoring one custom variable would do to its stored values
     *
     * Comes back null if nothing would actually change
     *
     * @return ?BaseHtmlElement
     */
    private function describeCustomPropertyMigration(BasketDiff $diff, string $uuid)
    {
        $migration = $diff->getCustomPropertyMigrationPreview($uuid);
        if ($migration->isNoop()) {
            return null;
        }

        if ($migration->blocked) {
            $affected = $diff->countStoredCustomVariableValues($migration->oldVarname);

            return Hint::warning(sprintf(
                $this->translate('Blocked, %s, %d stored value(s) stay as they are'),
                $this->describeBlockedCustomPropertyReason($migration),
                $affected
            ));
        }

        return $this->describeCustomPropertyMigrationChanges($migration, $diff);
    }

    /**
     * Same idea, but for a plan the caller already has on hand, and only ever
     * used once it's known not to be blocked
     *
     * @return ?BaseHtmlElement
     */
    private function describeCustomPropertyMigrationChanges(PropertyValueMigration $migration, BasketDiff $diff)
    {
        $lines = [];
        if ($migration->wholeValueCleared) {
            $lines[] = sprintf(
                $this->translate('Type is changing, every stored value under "%s" will be cleared'),
                $migration->oldVarname
            );
        } elseif ($migration->oldVarname !== $migration->newVarname) {
            $lines[] = sprintf('%s -> %s', $migration->oldVarname, $migration->newVarname);
        }

        $this->collectCustomPropertyChangeLines($migration->children, $lines);

        if (empty($lines)) {
            return null;
        }

        $affected = $diff->countStoredCustomVariableValues($migration->oldVarname);
        $items = [];
        foreach ($lines as $line) {
            $items[] = Html::tag('li', $line);
        }

        return Html::tag('div', ['class' => 'basket-property-migration'], [
            Html::tag('ul', $items),
            Html::tag('p', sprintf(
                $this->translate('%d host/service/etc already have a value stored under "%s"'),
                $affected,
                $migration->oldVarname
            )),
        ]);
    }

    /**
     * Turns a plan's nested changes into plain language lines
     *
     * @param PropertyValueChange[] $changes
     * @param string[] $lines added to as we go
     *
     * @return void
     */
    private function collectCustomPropertyChangeLines(array $changes, array &$lines, string $prefix = ''): void
    {
        foreach ($changes as $change) {
            $path = $prefix === '' ? $change->oldKey : $prefix . '.' . $change->oldKey;

            if ($change->newKey === null) {
                $lines[] = sprintf($this->translate('"%s" is being removed'), $path);
            } elseif ($change->valueCleared) {
                $lines[] = sprintf(
                    $this->translate('"%s" type is changing, its stored value will be cleared'),
                    $path
                );
            } elseif ($change->oldKey !== $change->newKey) {
                $lines[] = sprintf('"%s" -> "%s"', $path, $change->newKey);
            }

            if (! $change->valueCleared && ! empty($change->children)) {
                $this->collectCustomPropertyChangeLines(
                    $change->children,
                    $lines,
                    $change->newKey ?? $change->oldKey
                );
            }
        }
    }

    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function snapshotobjectAction()
    {
        $basket = $this->requireBasket();
        $snapshot = BasketSnapshot::load([
            'basket_uuid' => $basket->get('uuid'),
            'ts_create'   => $this->params->getRequired('ts'),
        ], $this->db());
        $snapshotUrl = $this->url()->without('type')->without('key')->setPath('director/basket/snapshot');
        $type = $this->params->get('type');
        $key = $this->params->get('key');

        $this->addTitle($this->translate('Single Object Diff'));
        $this->actions()->add([
            Link::create(
                $this->translate('back'),
                $snapshotUrl,
                null,
                ['class' => 'icon-left-big']
            ),
            /*
            Link::create(
                $this->translate('Restore'),
                $this->url()->with('action', 'restore'),
                null,
                ['class' => 'icon-rewind']
            )
            */
        ]);

        $this->addSingleTab($this->translate('Snapshot'));
        $targetDbName = $this->params->get('target_db');
        if ($targetDbName === null) {
            $connection = $this->db();
        } else {
            $connection = Db::fromResourceName($targetDbName);
        }
        $diff = new BasketDiff($snapshot, $connection);
        $object = $diff->getBasketObject($type, $key);
        if ($uuid = $object->uuid ?? null) {
            $uuid = Uuid::fromString($uuid);
        }

        // A custom variable's row is keyed by its uuid, not a name, show its real name instead.
        $displayKey = $type === 'CustomVariable' ? ($object->key_name ?? $key) : $key;
        $this->content()->add(Hint::info(Html::sprintf(
            $this->translate('Comparing %s "%s" from Snapshot "%s" to current config'),
            $type,
            $displayKey,
            Link::create(
                substr(bin2hex($snapshot->get('content_checksum')), 0, 7),
                $snapshotUrl,
                null,
                ['data-base-target' => '_next']
            )
        )));

        if ($type === 'CustomVariable') {
            $migrationHint = $this->describeCustomPropertyMigration($diff, $key);
            if ($migrationHint !== null) {
                $this->content()->add($migrationHint);
            }
        }

        $basketJson = $diff->getBasketString($type, $key);
        $currentJson = $diff->getCurrentString($type, $key, $uuid);
        if ($currentJson === $basketJson) {
            $this->content()->add([
                Hint::ok('Basket equals current object'),
                Html::tag('pre', $currentJson)
            ]);
        } else {
            $this->content()->add(new InlineDiff(new PhpDiff($currentJson, $basketJson)));
        }
    }

    /**
     * @return Basket
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function requireBasket()
    {
        return Basket::load($this->params->getRequired('name'), $this->db());
    }
}
