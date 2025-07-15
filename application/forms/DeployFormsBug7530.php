<?php

namespace Icinga\Module\Director\Forms;

use gipfl\Web\Widget\Hint;
use Icinga\Module\Director\Core\CoreApi;
use ipl\Html\Html;

trait DeployFormsBug7530
{
    public function hasBeenSubmitted()
    {
        if (parent::hasBeenSubmitted()) {
            return true;
        } else {
            return strlen($this->getSentValue('confirm_7530', '')) > 0;
        }
    }

    protected function shouldWarnAboutBug7530()
    {
        /** @var \Icinga\Module\Director\Db $db */
        $db = $this->getDb();

        return $db->settings()->get('ignore_bug7530') !== 'y'
            && $this->getSentValue('confirm_7530') !== 'i_know'
            && $this->configMightTriggerBug7530()
            & $this->coreHasBug7530();
    }

    protected function configMightTriggerBug7530()
    {
        /** @var \Icinga\Module\Director\Db $connection */
        $connection = $this->getDb();
        $db = $connection->getDbAdapter();

        $zoneIds = $db->fetchCol(
            $db->select()
                ->from('icinga_zone', 'id')
                ->where('object_type = ?', 'object')
        );
        if (empty($zoneIds)) {
            return false;
        }

        $objectTypes = [
            'icinga_host',
            'icinga_service',
            'icinga_notification',
            'icinga_command',
        ];

        foreach ($objectTypes as $objectType) {
            if (
                (int) $db->fetchOne(
                    $db->select()
                    ->from($objectType, 'COUNT(*)')
                    ->where('zone_id IN (?)', $zoneIds)
                ) > 0
            ) {
                return true;
            }
        }

        return false;
    }

    protected function coreHasBug7530()
    {
        // TODO: Cache this
        if ($this->api instanceof CoreApi) {
            $version = $this->api->getVersion();
            if ($version === null) {
                throw new \RuntimeException($this->translate('Unable to detect your Icinga 2 Core version'));
            } elseif (
                \version_compare($version, '2.11.0', '>=')
                && \version_compare($version, '2.12.0', '<')
            ) {
                return true;
            }
        }

        return false;
    }

    public function skipBecauseOfBug7530()
    {
        $bug7530 = $this->getSentValue('confirm_7530');
        if ($bug7530 === 'whaaat') {
            $this->setSuccessMessage($this->translate('Config has not been deployed'));
            parent::onSuccess();
        } elseif ($bug7530 === 'hell_yes') {
            $this->db->settings()->set('ignore_bug7530', 'y');
        }
        if ($this->shouldWarnAboutBug7530()) {
            $this->addHtml(Hint::warning(Html::sprintf($this->translate(
                "Warning: you're running Icinga v2.11.0 and our configuration looks"
                . " like you could face issue %s. We're already working on a solution."
                . " The GitHub Issue and our %s contain related details."
            ), Html::tag('a', [
                'href'   => 'https://github.com/Icinga/icinga2/issues/7530',
                'target' => '_blank',
                'title'  => sprintf(
                    $this->translate('Show Issue %s on GitHub'),
                    '7530'
                ),
                'class'  => 'icon-github-circled',
            ], '#7530'), Html::tag('a', [
                'href'   => 'https://icinga.com/docs/icinga2/latest/doc/16-upgrading-icinga-2/'
                    . '#config-sync-zones-in-zones',
                'target' => '_blank',
                'title'  => $this->translate('Upgrading Icinga 2 - Confic Sync: Zones in Zones'),
                'class' => 'icon-info-circled',
            ], $this->translate('Upgrading documentation')))));
            $this->addElement('select', 'confirm_7530', [
                'multiOptions' => $this->optionalEnum([
                    'i_know'   => $this->translate("I know what I'm doing, deploy anyway"),
                    'hell_yes' => $this->translate("I know, please don't bother me again"),
                    'whaaat'   => $this->translate("Thanks, I'll verify this and come back later"),
                ]),
                'class'      => 'autosubmit',
                'decorators' => ['ViewHelper'],
            ]);
            return true;
        }

        return false;
    }
}
