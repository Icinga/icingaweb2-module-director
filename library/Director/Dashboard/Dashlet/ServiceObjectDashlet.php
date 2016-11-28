<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ServiceObjectDashlet extends Dashlet
{
    protected $icon = 'services';

    protected $requiredStats = array('service', 'servicegroup', 'service_set');

    public function getTitle()
    {
        return $this->translate('Monitored Services');
    }

    public function getUrl()
    {
        return 'director/services';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }

    protected function onStatSummary($type, &$extra)
    {
        $view = $this->view;

        if (array_key_exists($type . '_set', $this->stats)) {
            $setstat = $this->stats[$type . '_set'];
            if ((int) $setstat->cnt_total === 0) {
                $extra[] = $view->translate('no related set exists');
            } else {
                if ((int) $setstat->cnt_template > 0) {
                    $extra[] = sprintf(
                        $view->translate('%s sets exist'),
                        $setstat->cnt_template
                    );
                }
                if ((int) $setstat->cnt_object > 0) {
                    $extra[] = sprintf(
                        $view->translate('%s sets are added to a host'),
                        $setstat->cnt_object
                    );
                }
            }
        }
    }
}
