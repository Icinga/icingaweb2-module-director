<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\HtmlDocument;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\SyncRun;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\NameValueTable;

class SyncRunDetails extends NameValueTable
{
    use TranslationHelper;

    /** @var SyncRun */
    protected $run;

    public function __construct(SyncRun $run)
    {
        $this->run = $run;
        $this->getAttributes()->add('data-base-target', '_next'); // eigentlich nur runSummary
        $this->addNameValuePairs([
            $this->translate('Start time') => $run->start_time,
            $this->translate('Duration') => sprintf('%.2fs', $run->duration_ms / 1000),
            $this->translate('Activity') => $this->runSummary($run)
        ]);
    }

    /**
     * @param SyncRun $run
     * @return array
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function runSummary(SyncRun $run)
    {
        $html = [];
        $total = $run->objects_deleted + $run->objects_created + $run->objects_modified;
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
            if ($run->last_former_activity === null) {
                return $html;
            }
            $formerId = $db->fetchActivityLogIdByChecksum($run->last_former_activity);
            $lastId = $db->fetchActivityLogIdByChecksum($run->last_related_activity);

            $idRangeEx = sprintf(
                'id>%d&id<=%d',
                $formerId,
                $lastId
            );
            $activityUrl = 'director/config/activities';

            $links = new HtmlDocument();
            $links->setSeparator(', ');
            if ($run->objects_created > 0) {
                $links->add(new Link(
                    sprintf('%d created', $run->objects_created),
                    $activityUrl,
                    ['action' => 'create', 'idRangeEx' => $idRangeEx]
                ));
            }
            if ($run->objects_modified > 0) {
                $links->add(new Link(
                    sprintf('%d modified', $run->objects_modified),
                    $activityUrl,
                    ['action' => 'modify', 'idRangeEx' => $idRangeEx]
                ));
            }
            if ($run->objects_deleted > 0) {
                $links->add(new Link(
                    sprintf('%d deleted', $run->objects_deleted),
                    $activityUrl,
                    ['action' => 'delete', 'idRangeEx' => $idRangeEx]
                ));
            }

            if (count($links) > 1) {
                $links->add(new Link(
                    'Show all actions',
                    $activityUrl,
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
}
