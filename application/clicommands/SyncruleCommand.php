<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\SyncRule;
use RuntimeException;

/**
 * Deal with Director Sync Rules
 *
 * Use this command to check or trigger your defined Sync Rules
 */
class SyncruleCommand extends Command
{
    /**
     * List defined Sync Rules
     *
     * This shows a table with your defined Sync Rules, their IDs and
     * current state. As triggering a Sync requires an ID, this is where
     * you can look up the desired ID.
     *
     * USAGE
     *
     * icingacli director syncrule list
     */
    public function listAction()
    {
        $rules = SyncRule::loadAll($this->db());
        if (empty($rules)) {
            echo "No Sync Rule has been defined\n";

            return;
        }

        printf("%4s | %s\n", 'ID', 'Sync Rule name');
        printf("-----+%s\n", str_repeat('-', 64));

        foreach ($rules as $rule) {
            $state = $rule->get('sync_state');
            printf("%4d | %s\n", $rule->get('id'), $rule->get('rule_name'));
            printf("     | -> %s%s\n", $state, $state === 'failing' ? ': ' . $rule->get('last_error_message') : '');
        }
    }

    /**
     * Check a given Sync Rule for changes
     *
     * This command runs a complete Sync in memory but doesn't persist eventual changes.
     *
     * USAGE
     *
     * icingacli director syncrule check --id <id>
     *
     * OPTIONS
     *
     *   --id <id>     A Sync Rule ID. Use the list command to figure out
     *   --benchmark   Show timing and memory usage details
     */
    public function checkAction()
    {
        $rule = $this->getSyncRule();
        $hasChanges = $rule->checkForChanges();
        $this->showSyncStateDetails($rule);
        if ($hasChanges) {
            $mods = $this->getExpectedModificationCounts($rule);
            printf(
                "Expected modifications: %dx create, %dx modify, %dx delete\n",
                $mods->modify,
                $mods->create,
                $mods->delete
            );
        }

        exit($this->getSyncStateExitCode($rule));
    }

    /**
     * This command deletes a Sync Rule.
     *
     * USAGE
     *
     * icingacli director syncrule delete --id <id>
     *
     * OPTIONS
     *
     *   --id <id>     A Sync Rule ID. Use the list command to figure out
     */
    public function deleteAction()
    {
        $rule = $this->getSyncRule();
        if ($rule->delete()) {
            echo sprintf("Sync rule '%s' has been deleted\n", $rule->get('rule_name'));
        }
    }

    protected function getExpectedModificationCounts(SyncRule $rule)
    {
        $modifications = $rule->getExpectedModifications();

        $create = 0;
        $modify = 0;
        $delete = 0;

        /** @var IcingaObject $object */
        foreach ($modifications as $object) {
            if ($object->hasBeenLoadedFromDb()) {
                if ($object->shouldBeRemoved()) {
                    $delete++;
                } else {
                    $modify++;
                }
            } else {
                $create++;
            }
        }

        return (object) [
            DirectorActivityLog::ACTION_CREATE => $create,
            DirectorActivityLog::ACTION_MODIFY => $modify,
            DirectorActivityLog::ACTION_DELETE => $delete,
        ];
    }

    /**
     * Trigger a Sync Run for a given Sync Rule
     *
     * This command builds new objects according your Sync Rule, compares them
     * with existing ones and persists eventual changes.
     *
     * USAGE
     *
     * icingacli director syncrule run --id <id>
     *
     * OPTIONS
     *
     *   --id <id>     A Sync Rule ID. Use the list command to figure out
     *   --benchmark   Show timing and memory usage details
     */
    public function runAction()
    {
        $rule = $this->getSyncRule();

        if ($rule->applyChanges()) {
            print "New data has been imported\n";
            $this->showSyncStateDetails($rule);
        } else {
            print "Nothing has been changed, imported data is still up to date\n";
        }
    }

    /**
     * @return SyncRule
     */
    protected function getSyncRule()
    {
        return SyncRule::loadWithAutoIncId(
            (int) $this->params->getRequired('id'),
            $this->db()
        );
    }

    /**
     * @param SyncRule $rule
     */
    protected function showSyncStateDetails(SyncRule $rule)
    {
        echo $this->getSyncStateDescription($rule) . "\n";
    }

    /**
     * @param SyncRule $rule
     * @return string
     */
    protected function getSyncStateDescription(SyncRule $rule)
    {
        switch ($rule->get('sync_state')) {
            case 'unknown':
                return "It's currently unknown whether we are in sync with this rule."
                    . ' You should either check for changes or trigger a new Sync Run.';
            case 'in-sync':
                return 'This Sync Rule is in sync';
            case 'pending-changes':
                return 'There are pending changes for this Sync Rule. You should'
                    . '  trigger a new Sync Run.';
            case 'failing':
                return 'This Sync Rule failed: ' . $rule->get('last_error_message');
            default:
                throw new RuntimeException('Invalid sync state: ' . $rule->get('sync_state'));
        }
    }

    /**
     * @param SyncRule $rule
     * @return string
     */
    protected function getSyncStateExitCode(SyncRule $rule)
    {
        switch ($rule->get('sync_state')) {
            case 'unknown':
                return 3;
            case 'in-sync':
                return 0;
            case 'pending-changes':
                return 1;
            case 'failing':
                return 2;
            default:
                throw new RuntimeException('Invalid sync state: ' . $rule->get('sync_state'));
        }
    }
}
