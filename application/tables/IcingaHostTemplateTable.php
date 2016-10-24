<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Tables\IcingaHostTable;

require_once __DIR__ . '/IcingaHostTable.php';
class IcingaHostTemplateTable extends IcingaHostTable
{
    protected $searchColumns = array(
        'host',
        'display_name'
    );

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'host'    => $view->translate('Template name'),
        );
    }

    protected function renderAdditionalActions($row)
    {
        $htm = '';
        $view = $this->view();

        if ($row->object_type === 'template') {
            $htm .= $view->qlink(
                '',
                'director/host/add?type=object',
                array('imports' => $row->host),
                array(
                    'class' => 'icon-plus',
                    'title' => $view->translate(
                        'Create a new host based on this template'
                    )
                )
            );
            if ($cnt = $row->cnt_child_templates) {
                if ((int) $cnt === 1) {
                    $title = $view->translate('Show one host template using this template');
                } else {
                    $title = sprintf(
                        $view->translate('Show %d host templates using this template'),
                        $cnt
                    );
                }

                $htm .= $view->qlink(
                    '',
                    'director/hosts/bytemplate',
                    array('name' => $row->host),
                    array(
                        'class' => 'icon-sitemap',
                        'title' => $title
                    )
                );

            }

            if ($cnt = $row->cnt_child_hosts) {
                if ((int) $cnt === 1) {
                    $title = $view->translate('Show one host using this template');
                } else {
                    $title = sprintf(
                        $view->translate('Show %d hosts using this template'),
                        $cnt
                    );
                }

                $htm .= $view->qlink(
                    '',
                    'director/hosts/bytemplate',
                    array('name' => $row->host),
                    array(
                        'class' => 'icon-host',
                        'title' => $title
                    )
                );

            }

        }

        return $htm;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('h.object_type = ?', 'template');
    }
}
