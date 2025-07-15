<?php

namespace Icinga\Module\Director\Web\Widget;

use Icinga\Module\Director\Objects\DirectorActivityLog;
use ipl\Html\HtmlDocument;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\SyncRun;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\NameValueTable;

use function sprintf;

class SyncRunDetails extends NameValueTable
{
    use TranslationHelper;

    public const URL_ACTIVITIES = 'director/config/activities';

    /** @var SyncRun */
    protected $run;

    public function __construct(SyncRun $run)
    {
        $this->run = $run;
        $this->getAttributes()->add('data-base-target', '_next'); // eigentlich nur runSummary
        $this->addNameValuePairs([
            $this->translate('Start time') => $run->get('start_time'),
            $this->translate('Duration')   => sprintf('%.2fs', $run->get('duration_ms') / 1000),
            $this->translate('Activity')   => $this->runSummary($run)
        ]);
    }

    /**
     * @param SyncRun $run
     * @return array
     */
    protected function runSummary(SyncRun $run)
    {
        $html = [];
        $total = $run->countActivities();
        if ($total === 0) {
            $html[] = $this->translate('No changes have been made');
        } else {
            if ($total === 1) {
                $html[] = $this->translate('One object has been modified');
            } else {
                $html[] = sprintf(
                    $this->translate('%s objects have been modified'),
                    $total
                );
            }

            /** @var Db $db */
            $db = $run->getConnection();
            $formerId = $db->fetchActivityLogIdByChecksum($run->get('last_former_activity'));
            if ($formerId === null) {
                return $html;
            }
            $lastId = $db->fetchActivityLogIdByChecksum($run->get('last_related_activity'));

            if ($formerId !== $lastId) {
                $idRangeEx = sprintf(
                    'id>%d&id<=%d',
                    $formerId,
                    $lastId
                );
            } else {
                $idRangeEx = null;
            }

            $links = new HtmlDocument();
            $links->setSeparator(', ');
            $links->add([
                $this->activitiesLink(
                    'objects_created',
                    $this->translate('%d created'),
                    DirectorActivityLog::ACTION_CREATE,
                    $idRangeEx
                ),
                $this->activitiesLink(
                    'objects_modified',
                    $this->translate('%d modified'),
                    DirectorActivityLog::ACTION_MODIFY,
                    $idRangeEx
                ),
                $this->activitiesLink(
                    'objects_deleted',
                    $this->translate('%d deleted'),
                    DirectorActivityLog::ACTION_DELETE,
                    $idRangeEx
                ),
            ]);

            if ($idRangeEx && count($links) > 1) {
                $links->add(new Link(
                    $this->translate('Show all actions'),
                    self::URL_ACTIVITIES,
                    ['idRangeEx' => $idRangeEx]
                ));
            }

            if (! $links->isEmpty()) {
                $html[] = ': ';
                $html[] = $links;
            }
        }

        return $html;
    }

    protected function activitiesLink($key, $label, $action, $rangeFilter)
    {
        $count = $this->run->get($key);
        if ($count > 0) {
            if ($rangeFilter) {
                return new Link(
                    sprintf($label, $count),
                    self::URL_ACTIVITIES,
                    ['action' => $action, 'idRangeEx' => $rangeFilter]
                );
            }

            return sprintf($label, $count);
        }

        return null;
    }
}
