<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Data;

use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Data\ObjectImporter;
use Icinga\Module\Director\Job\ImportJob;
use Icinga\Module\Director\Job\SyncJob;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Test\BaseTestCase;

class JobExportTest extends BaseTestCase
{
    protected const JOB_NAME = '___TEST___job_export';

    public function testImportJobForAllSourcesSurvivesExportAndImport(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $job = $this->roundTrip(ImportJob::class, ['source_id' => '__ALL__', 'run_import' => 'y']);

        $this->assertSame('__ALL__', $job->getSetting('source_id'));
        $this->assertSame('y', $job->getSetting('run_import'));
    }

    public function testSyncJobForAllRulesSurvivesExportAndImport(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $job = $this->roundTrip(SyncJob::class, ['rule_id' => '__ALL__', 'apply_changes' => 'y']);

        $this->assertSame('__ALL__', $job->getSetting('rule_id'));
        $this->assertSame('y', $job->getSetting('apply_changes'));
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            DirectorJob::loadOptional(self::JOB_NAME, $this->getDb())?->delete();
        }

        parent::tearDown();
    }

    /**
     * Store a job, export it, delete it and import the export like a basket restore does
     *
     * @param class-string $jobClass
     * @param array<string, string> $settings
     *
     * @return DirectorJob The imported job, loaded from the database
     */
    protected function roundTrip(string $jobClass, array $settings): DirectorJob
    {
        $db = $this->getDb();
        $job = DirectorJob::create([
            'job_name'     => self::JOB_NAME,
            'job_class'    => $jobClass,
            'disabled'     => 'n',
            'run_interval' => 900,
            'settings'     => $settings,
        ], $db);
        $job->store();

        $exported = (new Exporter($db))->export($job);
        $job->delete();

        (new ObjectImporter($db))->import(DirectorJob::class, $exported)->store();

        return DirectorJob::load(self::JOB_NAME, $db);
    }
}
