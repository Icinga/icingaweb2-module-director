<?php

namespace Icinga\Module\Director\Web\Widget;

use Icinga\Module\Director\Db;
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
     * Render one diff block per selected entry, followed by a combined restore
     * form for whichever of them have an old state to restore
     */
    protected function assemble()
    {
        $restoreItems = [];

        foreach ($this->ids as $id) {
            $entry = $this->db->fetchActivityLogEntryById($id);
            if ($entry === null) {
                continue;
            }

            $info = new ActivityLogInfo($this->db, $entry->object_type, $entry->object_name);
            $info->setEmbedded(true)->setId($id);
            // populates $info's default tab based on the entry's action, has to
            // happen before showTab() or it won't know which diff to render
            $info->getTabs(Url::fromPath('director/config/activity'));

            $this->add(Html::tag('h2', null, $info->getTitle()));
            $info->showTab(null);
            $this->add($info);

            if ($entry->action_name === DirectorActivityLog::ACTION_CREATE) {
                // there is no old state for a create activity, restoring it means
                // getting rid of the object again
                $restoreItems[] = [
                    'object' => ActivityLogInfo::createObjectFromProperties(
                        $entry->object_type,
                        $entry->new_properties,
                        $this->db
                    ),
                    'lookup' => null,
                    'delete' => true,
                ];
            } elseif ($entry->old_properties) {
                // a rename ends up living under its new name, that's the one we
                // need to find the live object, not the old name we restore to
                $lookup = $entry->action_name === DirectorActivityLog::ACTION_MODIFY
                    ? ActivityLogInfo::createObjectFromProperties($entry->object_type, $entry->new_properties, $this->db)
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

        if (! empty($restoreItems)) {
            $this->add(
                BulkRestoreObjectForm::load()
                    ->setDb($this->db)
                    ->setItems($restoreItems)
                    ->handleRequest()
            );
        }
    }
}
