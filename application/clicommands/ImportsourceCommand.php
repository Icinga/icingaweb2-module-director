<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Benchmark;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\ImportSource;

/**
 * Deal with Director Import Sources
 *
 * Use this command to check or trigger your defined Import Sources
 */
class ImportsourceCommand extends Command
{
    /**
     * List defined Import Sources
     *
     * This shows a table with your defined Import Sources, their IDs and
     * current state. As triggering Imports requires an ID, this is where
     * you can look up the desired ID.
     *
     * USAGE
     *
     * icingacli director importsource list
     */
    public function listAction()
    {
        $sources = ImportSource::loadAll($this->db());
        if (empty($sources)) {
            echo "No Import Source has been defined\n";

            return;
        }

        printf("%4s | %s\n", 'ID', 'Import Source name');
        printf("-----+%s\n", str_repeat('-', 64));

        foreach ($sources as $source) {
            $state = $source->get('import_state');
            printf("%4d | %s\n", $source->get('id'), $source->get('source_name'));
            printf("     | -> %s%s\n", $state, $state === 'failing' ? ': ' . $source->get('last_error_message') : '');
        }
    }

    /**
     * Check a given Import Source for changes
     *
     * This command fetches data from the given Import Source and compares it
     * to the most recently imported data.
     *
     * USAGE
     *
     * icingacli director importsource check --id <id>
     *
     * OPTIONS
     *
     *   --id <id>     An Import Source ID. Use the list command to figure out
     *   --benchmark   Show timing and memory usage details
     */
    public function checkAction()
    {
        $source = $this->getImportSource();
        $source->checkForChanges();
        $this->showImportStateDetails($source);
    }

    /**
     * This command deletes the given Import Source.
     *
     * USAGE
     *
     * icingacli director importsource delete --id <id>
     *
     * OPTIONS
     *
     *   --id <id>     An Import Source ID. Use the list command to figure out
     */
    public function deleteAction()
    {
        $source = $this->getImportSource();
        if ($source->delete()) {
            echo sprintf("Import Source '%s' has been deleted\n", $source->get('source_name'));
        }
    }

    /**
     * Fetch current data from a given Import Source
     *
     * This command fetches data from the given Import Source and outputs
     * them as plain JSON
     *
     * USAGE
     *
     * icingacli director importsource fetch --id <id>
     *
     * OPTIONS
     *
     *   --id <id>     An Import Source ID. Use the list command to figure out
     *   --benchmark   Show timing and memory usage details
     */
    public function fetchAction()
    {
        $source = $this->getImportSource();
        $source->checkForChanges();
        $hook = ImportSourceHook::forImportSource($source);
        Benchmark::measure('Ready to fetch data');
        $data = $hook->fetchData();
        $source->applyModifiers($data);
        Benchmark::measure(sprintf('Got %d rows, ready to dump JSON', count($data)));
        echo Json::encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Trigger an Import Run for a given Import Source
     *
     * This command fetches data from the given Import Source and stores it to
     * the Director DB, so that the next related Sync Rule run can work with
     * fresh data. In case data didn't change, nothing is going to be stored.
     *
     * USAGE
     *
     * icingacli director importsource run --id <id>
     *
     * OPTIONS
     *
     *   --id <id>     An Import Source ID. Use the list command to figure out
     *   --benchmark   Show timing and memory usage details
     */
    public function runAction()
    {
        $source = $this->getImportSource();

        if ($source->runImport()) {
            print "New data has been imported\n";
            $this->showImportStateDetails($source);
        } else {
            print "Nothing has been changed, imported data is still up to date\n";
        }
    }

    /**
     * @return ImportSource
     */
    protected function getImportSource()
    {
        return ImportSource::loadWithAutoIncId(
            (int) $this->params->getRequired('id'),
            $this->db()
        );
    }

    /**
     * @param ImportSource $source
     * @throws \Icinga\Exception\IcingaException
     */
    protected function showImportStateDetails(ImportSource $source)
    {
        echo $this->getImportStateDescription($source) . "\n";
    }

    /**
     * @param ImportSource $source
     * @return string
     * @throws \Icinga\Exception\IcingaException
     */
    protected function getImportStateDescription(ImportSource $source)
    {
        switch ($source->get('import_state')) {
            case 'unknown':
                return "It's currently unknown whether we are in sync with this"
                    . ' Import Source. You should either check for changes or'
                    . ' trigger a new Import Run.';
            case 'in-sync':
                return 'This Import Source is in sync';
            case 'pending-changes':
                return 'There are pending changes for this Import Source. You'
                    . ' should trigger a new Import Run.';
            case 'failing':
                return 'This Import Source failed: ' . $source->get('last_error_message');
            default:
                return 'This Import Source has an invalid state: ' . $source->get('import_state');
        }
    }
}
