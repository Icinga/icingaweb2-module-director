<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Tests\Icinga\Module\Director\Objects\Lib\DirectorJobConcurrentSettingsTestJob;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Test\BaseTestCase;

class DirectorJobRunTest extends BaseTestCase
{
    public function testRunningJobDoesNotOverwriteSettingsChangedDuringExecution(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $name = '___TEST___concurrent_job_settings';
        $job = DirectorJob::create([
            'job_name'     => $name,
            'job_class'    => DirectorJobConcurrentSettingsTestJob::class,
            'run_interval' => 60,
            'disabled'     => 'n',
        ], $db);
        $job->set('run_import', 'n');
        $job->store();

        try {
            $runningJob = DirectorJob::load($name, $db);
            DirectorJobConcurrentSettingsTestJob::$duringRun = function () use ($db, $name): void {
                $editedJob = DirectorJob::load($name, $db);
                $editedJob->set('run_import', 'y');
                $editedJob->set('run_interval', 120);
                $editedJob->store();
            };

            $this->assertTrue($runningJob->run());
            $this->assertSame('y', $runningJob->getSetting('run_import'));
            $this->assertFalse($runningJob->hasBeenModified());
            $runningJob->store();

            $storedJob = DirectorJob::load($name, $db);
            $this->assertSame('y', $storedJob->getSetting('run_import'));
            $this->assertSame('120', (string) $storedJob->get('run_interval'));
            $this->assertSame('y', $storedJob->get('last_attempt_succeeded'));
            $this->assertNotNull($storedJob->get('ts_last_attempt'));
        } finally {
            DirectorJobConcurrentSettingsTestJob::$duringRun = null;
            $job->delete();
        }
    }
}
