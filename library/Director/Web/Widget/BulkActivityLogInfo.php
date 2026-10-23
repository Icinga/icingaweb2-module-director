<?php

namespace Icinga\Module\Director\Web\Widget;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\ActivityLogNoteForm;
use Icinga\Module\Director\Forms\BulkRestoreObjectForm;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use gipfl\IcingaWeb2\Url;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\I18n\Translation;

class BulkActivityLogInfo extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'div';

    /** @var Db */
    protected $db;

    /** @var int[] ids to show/restore, newest first */
    protected $ids;

    public function __construct(Db $db, array $ids)
    {
        $this->db = $db;
        $this->ids = $ids;
    }

    /**
     * Show a summary first, then a diff for each selected entry and one restore
     * form for all of them
     */
    protected function assemble()
    {
        $selected = [];
        $noteIds = [];
        foreach ($this->ids as $id) {
            $entry = $this->db->fetchActivityLogEntryById($id);
            if ($entry === null) {
                continue;
            }

            $noteIds[] = (int) $id;
            $info = new ActivityLogInfo($this->db, $entry->object_type, $entry->object_name);
            $info->setEmbedded(true)->setId($id);
            // Pick the entry's tab first, or showing it has no diff to render
            $info->getTabs(Url::fromPath('director/config/activity'));
            $selected[] = ['entry' => $entry, 'info' => $info, 'restorable' => $this->isRestorable($entry)];
        }

        $this->add($this->createSummary($selected));

        $restoreItems = [];
        foreach ($selected as $item) {
            $entry = $item['entry'];
            $info = $item['info'];

            $this->add(Html::tag('h2', [
                'id'    => $this->getAnchorId($entry),
                'class' => [$this->getActionIcon($entry), 'action-' . $entry->action_name],
            ], $info->getTitle()));
            $info->showTab(null);
            $this->add($info);

            if ($entry->action_name === DirectorActivityLog::ACTION_CREATE) {
                // A created entry has no old state, restoring it deletes the object
                $restoreItems[] = [
                    'object' => ActivityLogInfo::createObjectFromProperties(
                        $entry->object_type,
                        $entry->new_properties,
                        $this->db
                    ),
                    'lookup' => null,
                    'delete' => true,
                ];
            } elseif ($item['restorable']) {
                // After a rename the live object has the new name, find it by that
                $lookup = $entry->action_name === DirectorActivityLog::ACTION_MODIFY
                    ? ActivityLogInfo::createObjectFromProperties(
                        $entry->object_type,
                        $entry->new_properties,
                        $this->db,
                        $entry->object_name
                    )
                    : null;

                $restoreItems[] = [
                    'object' => ActivityLogInfo::createObjectFromProperties(
                        $entry->object_type,
                        $entry->old_properties,
                        $this->db
                    ),
                    'lookup' => $lookup,
                    'delete' => false,
                ];
            }
        }

        if (! empty($selected)) {
            $this->add($this->createNoteForm($noteIds));
        }

        if (! empty($restoreItems)) {
            $this->add($this->createRestoreForm($restoreItems));
        }
    }

    /**
     * One note for all selected entries
     *
     * @param int[] $ids
     *
     * @return ActivityLogNoteForm
     */
    protected function createNoteForm(array $ids)
    {
        return (new ActivityLogNoteForm($this->db, $ids))
            ->handleRequest(ServerRequest::fromGlobals());
    }

    /**
     * @param array $restoreItems Newest change first
     *
     * @return BulkRestoreObjectForm
     */
    protected function createRestoreForm(array $restoreItems)
    {
        return BulkRestoreObjectForm::load()
            ->setDb($this->db)
            ->setItems($restoreItems)
            ->handleRequest();
    }

    /**
     * Check if restoring the entry does anything
     *
     * Entries that only saved custom variables have no name in their old state
     * and can't be restored.
     *
     * @param object $entry
     *
     * @return bool
     */
    protected function isRestorable(object $entry)
    {
        if ($entry->action_name === DirectorActivityLog::ACTION_CREATE) {
            return true;
        }

        return $entry->old_properties
            && property_exists(json_decode($entry->old_properties), 'object_name');
    }

    /**
     * Show what is about to be undone before the diffs start
     *
     * @param array<int, array{entry: object, info: ActivityLogInfo, restorable: bool}> $selected
     *
     * @return BaseHtmlElement
     */
    protected function createSummary(array $selected)
    {
        $actions = $restorable = [
            DirectorActivityLog::ACTION_CREATE => 0,
            DirectorActivityLog::ACTION_MODIFY => 0,
            DirectorActivityLog::ACTION_DELETE => 0,
        ];
        $skipped = 0;
        $rows = [];
        foreach ($selected as $item) {
            $action = $item['entry']->action_name;
            $actions[$action]++;
            if ($item['restorable']) {
                $restorable[$action]++;
            } else {
                $skipped++;
            }

            $rows[] = $this->createSummaryRow($item);
        }

        $summary = Html::tag('div', ['class' => 'bulk-activity-summary']);
        $summary->add($this->createChangeLine(count($selected), $actions));
        $summary->add($this->createRestoreLine($restorable, $skipped));
        $summary->add(Html::tag('table', ['class' => 'common-table'], [
            Html::tag('thead', null, Html::tag('tr', null, [
                Html::tag('th', null, $this->translate('Time')),
                Html::tag('th', null, $this->translate('Author')),
                Html::tag('th', null, $this->translate('Change')),
                Html::tag('th', null, $this->translate('On restore')),
            ])),
            Html::tag('tbody', null, $rows),
        ]));

        return $summary;
    }

    /**
     * One row of the overview, a click on it jumps to the diff
     *
     * @param array{entry: object, info: ActivityLogInfo, restorable: bool} $item
     *
     * @return BaseHtmlElement
     */
    protected function createSummaryRow(array $item)
    {
        $entry = $item['entry'];
        $anchor = '#' . $this->getAnchorId($entry);

        // Icinga treats a click on a row with an href like a click on a link
        return Html::tag('tr', ['href' => $anchor, 'class' => 'action-' . $entry->action_name], [
            Html::tag('td', null, $entry->change_time),
            Html::tag('td', null, $entry->author),
            Html::tag('td', null, Html::tag('a', ['href' => $anchor], $item['info']->getTitle())),
            Html::tag('td', null, $this->createBadge(
                $this->describeRestore($entry, $item['restorable']),
                'undo-' . ($item['restorable'] ? $entry->action_name : 'skipped')
            )),
        ]);
    }

    /**
     * Count the selected changes by what they did
     *
     * @param int $total
     * @param array<string, int> $actions How many entries there are for each action
     *
     * @return BaseHtmlElement
     */
    protected function createChangeLine(int $total, array $actions)
    {
        $line = Html::tag('p', ['class' => 'activity-counts'], Html::tag(
            'strong',
            null,
            sprintf($this->translate('%d changes selected'), $total)
        ));
        $texts = [
            DirectorActivityLog::ACTION_CREATE => $this->translate('%d created'),
            DirectorActivityLog::ACTION_MODIFY => $this->translate('%d modified'),
            DirectorActivityLog::ACTION_DELETE => $this->translate('%d deleted'),
        ];
        foreach ($texts as $action => $text) {
            if ($actions[$action] > 0) {
                $line->add($this->createBadge(sprintf($text, $actions[$action]), 'action-' . $action));
            }
        }

        return $line;
    }

    /**
     * Count what the restore button does, in the opposite colors of the changes
     *
     * A created entry gets deleted again and a deleted one gets created again.
     *
     * @param array<string, int> $restorable How many entries there are for each action
     * @param int $skipped
     *
     * @return BaseHtmlElement
     */
    protected function createRestoreLine(array $restorable, int $skipped)
    {
        $line = Html::tag('p', ['class' => 'activity-counts']);
        if (array_sum($restorable) === 0) {
            $line->add($this->translate('None of the selected changes can be restored'));

            return $line;
        }

        $line->add(Html::tag(
            'strong',
            null,
            $this->translate('Restoring goes from the newest to the oldest change')
        ));
        $texts = [
            DirectorActivityLog::ACTION_CREATE => $this->translate('%d deleted'),
            DirectorActivityLog::ACTION_MODIFY => $this->translate('%d restored'),
            DirectorActivityLog::ACTION_DELETE => $this->translate('%d re-created'),
        ];
        foreach ($texts as $action => $text) {
            if ($restorable[$action] > 0) {
                $line->add($this->createBadge(sprintf($text, $restorable[$action]), 'undo-' . $action));
            }
        }

        if ($skipped > 0) {
            $line->add($this->createBadge(sprintf($this->translate('%d skipped'), $skipped), 'undo-skipped'));
        }

        return $line;
    }

    /**
     * @param string $text
     * @param string $class What decides the color, an action-* or undo-* class
     *
     * @return BaseHtmlElement
     */
    protected function createBadge(string $text, string $class)
    {
        return Html::tag('span', ['class' => ['activity-badge', $class]], $text);
    }

    /**
     * Id of the diff heading, the overview links to it
     *
     * @param object $entry
     *
     * @return string
     */
    protected function getAnchorId(object $entry)
    {
        return 'activity-' . $entry->id;
    }

    /**
     * @param object $entry
     *
     * @return string
     */
    protected function getActionIcon(object $entry)
    {
        return match ($entry->action_name) {
            DirectorActivityLog::ACTION_CREATE => 'icon-plus',
            DirectorActivityLog::ACTION_DELETE => 'icon-cancel',
            default => 'icon-wrench',
        };
    }

    /**
     * @param object $entry
     * @param bool $restorable
     *
     * @return string
     */
    protected function describeRestore(object $entry, bool $restorable)
    {
        if (! $restorable) {
            return $this->translate('Can not be restored, skipped');
        }

        return match ($entry->action_name) {
            DirectorActivityLog::ACTION_CREATE => $this->translate('Object gets deleted'),
            DirectorActivityLog::ACTION_DELETE => $this->translate('Object gets re-created'),
            default => $this->translate('Former state gets restored'),
        };
    }
}
