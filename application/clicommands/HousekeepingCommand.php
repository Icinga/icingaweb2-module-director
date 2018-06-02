<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Exception\MissingParameterException;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Db\Housekeeping;
use Icinga\Module\Director\Db\MembershipHousekeeping;

class HousekeepingCommand extends Command
{
    protected $housekeeping;

    public function tasksAction()
    {
        if ($pending = $this->params->shift('pending')) {
            $tasks = $this->housekeeping()->getPendingTaskSummary();
        } else {
            $tasks = $this->housekeeping()->getTaskSummary();
        }

        $len = array_reduce(
            $tasks,
            function ($max, $task) {
                return max(
                    $max,
                    strlen($task->title) + strlen($task->name) + 3
                );
            }
        );

        if (count($tasks)) {
            print "\n";
            printf(" %-" . $len . "s | %s\n", 'Housekeeping task (name)', 'Count');
            printf("-%-" . $len . "s-|-------\n", str_repeat('-', $len));
        }

        foreach ($tasks as $task) {
            printf(
                " %-" . $len . "s | %5d\n",
                sprintf('%s (%s)', $task->title, $task->name),
                $task->count
            );
        }

        if (count($tasks)) {
            print "\n";
        }
    }

    public function runAction()
    {
        if (!$job = $this->params->shift()) {
            throw new MissingParameterException(
                'Job is required, say ALL to run all pending jobs'
            );
        }

        if ($job === 'ALL') {
            $this->housekeeping()->runAllTasks();
        } else {
            $this->housekeeping()->runTask($job);
        }
    }

    /**
     * Check and repair membership cache
     *
     * Options:
     *   --type host  Set the object type (Only host supported currently)
     *   --apply      Actually update the database
     */
    public function membershipsAction()
    {
        $type = $this->params->get('type', 'host');
        $apply = $this->params->shift('apply');

        /** @var MembershipHousekeeping $class */
        $class = 'Icinga\\Module\\Director\\Db\\' . ucfirst($type) . 'MembershipHousekeeping';
        /** @var MembershipHousekeeping $helper */
        $helper = new $class($this->db());

        printf("Checking %s memberships\n", $type);

        list($new, $outdated) = $helper->check();
        $newCount = count($new);
        $outdatedCount = count($outdated);
        $objects = $helper->getObjects();
        $groups = $helper->getGroups();

        printf("%d objects - %d groups\n", count($objects), count($groups));

        printf("Found %d new and %d outdated mappings\n", $newCount, $outdatedCount);

        if ($apply && $newCount > 0 && $outdatedCount > 0) {
            $helper->update();
            printf("Update complete.\n");
        }
    }

    protected function housekeeping()
    {
        if ($this->housekeeping === null) {
            $this->housekeeping = new Housekeeping($this->db());
        }

        return $this->housekeeping;
    }
}
