<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Date\DateFormatter;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\DirectorObject\ObjectPurgeHelper;

/**
 * Export Director Config Objects
 */
class BasketCommand extends Command
{
    /**
     * List configured Baskets
     *
     * USAGE
     *
     * icingacli director basket list
     *
     * OPTIONS
     */
    public function listAction()
    {
        $db = $this->db()->getDbAdapter();
        $query = $db->select()
            ->from('director_basket', 'basket_name')
            ->order('basket_name');
        foreach ($db->fetchCol($query) as $name) {
            echo "$name\n";
        }
    }

    /**
     * JSON-dump for objects related to the given Basket
     *
     * USAGE
     *
     * icingacli director basket dump --name <basket>
     *
     * OPTIONS
     */
    public function dumpAction()
    {
        $basket = $this->requireBasket();
        $snapshot = BasketSnapshot::createForBasket($basket, $this->db());
        echo $snapshot->getJsonDump() . "\n";
    }

    /**
     * Take a snapshot for the given Basket
     *
     * USAGE
     *
     * icingacli director basket snapshot --name <basket>
     *
     * OPTIONS
     */
    public function snapshotAction()
    {
        $basket = $this->requireBasket();
        $snapshot = BasketSnapshot::createForBasket($basket, $this->db());
        $snapshot->store();
        $hexSum = bin2hex($snapshot->get('content_checksum'));
        printf(
            "Snapshot '%s' taken for Basket '%s' at %s\n",
            substr($hexSum, 0, 7),
            $basket->get('basket_name'),
            DateFormatter::formatDateTime($snapshot->get('ts_create') / 1000)
        );
    }

    /**
     * Restore a Basket from JSON dump provided on STDIN
     *
     * USAGE
     *
     * icingacli director basket restore < basket-dump.json
     *
     * OPTIONS
     *   --purge <ObjectType>[,<ObjectType] Purge objects of the
     *     Given types. WARNING: this removes ALL objects that are
     *     not shipped with the given basket
     *   --force Purge refuses to purge Objects in case there are
     *     no Objects of a given ObjectType in the provided basket
     *     unless forced to do so
     */
    public function restoreAction()
    {
        if ($purge = $this->params->get('purge')) {
            $purge = explode(',', $purge);
            ObjectPurgeHelper::assertObjectTypesAreEligibleForPurge($purge);
        }
        $json = file_get_contents('php://stdin');
        BasketSnapshot::restoreJson($json, $this->db());
        if ($purge) {
            $this->purgeObjectTypes(Json::decode($json), $purge, $this->params->get('force'));
        }
        echo "Objects from Basket Snapshot have been restored\n";
    }

    protected function purgeObjectTypes($objects, array $types, $force = false)
    {
        $helper = new ObjectPurgeHelper($this->db());
        if ($force) {
            $helper->force();
        }
        foreach ($types as $type) {
            list($className, $typeFilter) = BasketSnapshot::getClassAndObjectTypeForType($type);
            $helper->purge(
                isset($objects->$type) ? (array) $objects->$type : [],
                $className,
                $typeFilter
            );
        }
    }

    /**
     */
    protected function requireBasket()
    {
        return Basket::load($this->params->getRequired('name'), $this->db());
    }
}
