<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Application\Icinga;
use Icinga\Application\Test;
use Icinga\Module\Director\Forms\IcingaHostForm;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaTemplateChoiceHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Web\Request;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;

/**
 * Submits the real host form, so the confirmation that guards values lost to a source change runs with a posted answer
 */
class IcingaHostFormSourceChangeTest extends BaseTestCase
{
    use CommandVariableFixture;

    protected ?string $acceptHeader = null;

    protected ?string $requestMethod = null;

    protected ?Request $appRequest = null;

    /** @var array<string, mixed> */
    protected array $post = [];

    protected ?IcingaTemplateChoiceHost $choice = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? null;
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $this->post = $_POST;
    }

    public function testAnsweringNoKeepsTheCheckCommandAndSavesTheOtherChanges(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createCommandFixtures();

        $form = $this->submit('linux', [
            'imports'                => [$this->fx('ssh')],
            'check_command'          => $this->fx('check-ping'),
            'notes'                  => 'Changed',
            'confirm_remove_imports' => 'n',
        ]);

        $this->assertSame('Confirm check command change', $form->getElement('confirm_remove_imports')?->getLabel());
        $template = IcingaHost::load($this->fx('linux'), $this->getDb());
        $this->assertSame($this->fx('check-disk'), $template->get('check_command'));
        $this->assertSame('Changed', $template->get('notes'), 'the other changes are saved');
        $this->assertSame('90%', $template->get('vars.' . $this->fx('warning')));
    }

    public function testAnsweringYesChangesTheCheckCommandAndRemovesTheValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createCommandFixtures();

        $this->submit('linux', [
            'imports'                => [$this->fx('ssh')],
            'check_command'          => $this->fx('check-ping'),
            'confirm_remove_imports' => 'y',
        ]);

        $template = IcingaHost::load($this->fx('linux'), $this->getDb());
        $this->assertSame($this->fx('check-ping'), $template->get('check_command'));
        $this->assertNull($template->get('vars.' . $this->fx('warning')));
    }

    public function testTheLabelNamesWhatTheSubmissionRemoves(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createCommandFixtures();

        $form = $this->submit('linux', ['imports' => [], 'check_command' => $this->fx('check-disk')]);
        $this->assertSame('Confirm import removal', $form->getElement('confirm_remove_imports')?->getLabel());
        $this->assertNotNull($form->getElement('import_removal_warning'));
        $this->assertNull($form->getElement('check_command_change_warning'));

        $form = $this->submit('linux', ['imports' => [], 'check_command' => $this->fx('check-ping')]);
        $this->assertSame(
            'Confirm import removal and check command change',
            $form->getElement('confirm_remove_imports')?->getLabel()
        );
        $this->assertNotNull($form->getElement('import_removal_warning'));
        $this->assertNotNull($form->getElement('check_command_change_warning'));

        $template = IcingaHost::load($this->fx('linux'), $this->getDb());
        $this->assertSame([$this->fx('ssh')], $template->getImports(), 'no answer keeps the imports');
        $this->assertSame($this->fx('check-disk'), $template->get('check_command'));
    }

    public function testNoConfirmationIsAskedWhenNothingIsLost(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createCommandFixtures();

        $form = $this->submit('linux', [
            'imports'       => [$this->fx('ssh')],
            'check_command' => $this->fx('check-disk'),
            'notes'         => 'Changed',
        ]);

        $this->assertNull($form->getElement('confirm_remove_imports'));
        $this->assertSame('Changed', IcingaHost::load($this->fx('linux'), $this->getDb())->get('notes'));
    }

    public function testATemplateWithNothingToImportStillWarns(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->createFixtureCommand('check-ping', 'object');
        $template = $this->createFixtureHost('linux', 'template', [], 'check-disk');
        $this->storeFixtureValue($template, 'warning', '90%', $warning);

        $form = $this->submit('linux', ['check_command' => $this->fx('check-ping')]);

        $this->assertNull($form->getElement('imports'), 'no other host template exists to import');
        $this->assertSame('Confirm check command change', $form->getElement('confirm_remove_imports')?->getLabel());
        $this->assertNotNull($form->getElement('check_command_change_warning'));
        $this->assertSame(
            $this->fx('check-disk'),
            IcingaHost::load($this->fx('linux'), $this->getDb())->get('check_command')
        );
    }

    public function testAChosenTemplateCountsAsAnImport(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('check-os', 'object', [], [$mode]);
        $this->createFixtureHost('generic', 'template');
        $linux = $this->createFixtureHost('os-linux', 'template', [], 'check-os');
        $this->choice = IcingaTemplateChoiceHost::create([
            'object_name'  => $this->fx('os'),
            'min_required' => 0,
            'max_allowed'  => 1,
        ], $this->getDb());
        $this->choice->setMembers([$linux->getObjectName()]);
        $this->choice->store();
        $host = $this->createFixtureHost('web01', 'object', ['generic', 'os-linux']);
        $this->storeFixtureValue($host, 'mode', 'fast', $mode);

        $form = $this->submit('web01', [
            'object_type'                    => 'object',
            'imports'                        => [$this->fx('generic')],
            'choice' . $this->choice->get('id') => $this->fx('os-linux'),
            'notes'                          => 'Changed',
        ]);

        $this->assertNull(
            $form->getElement('confirm_remove_imports'),
            'the template stays chosen, so the host keeps its check command'
        );
        $host = IcingaHost::load($this->fx('web01'), $this->getDb());
        $this->assertSame('Changed', $host->get('notes'));
        $this->assertSame('fast', $host->get('vars.' . $this->fx('mode')));
    }

    public function testAChoiceWhoseRequiredTemplateIsRemovedNoLongerCountsAsAnImport(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('check-os', 'object', [], [$mode]);
        $generic = $this->createFixtureHost('generic', 'template');
        $this->createFixtureHost('base', 'template');
        $linux = $this->createFixtureHost('os-linux', 'template', [], 'check-os');
        $this->choice = IcingaTemplateChoiceHost::create([
            'object_name'          => $this->fx('os'),
            'min_required'         => 0,
            'max_allowed'          => 1,
            'required_template_id' => $generic->get('id'),
        ], $this->getDb());
        $this->choice->setMembers([$linux->getObjectName()]);
        $this->choice->store();
        $host = $this->createFixtureHost('web01', 'object', ['generic', 'os-linux']);
        $this->storeFixtureValue($host, 'mode', 'fast', $mode);

        // The choice field is still posted, but without its required template the form drops it
        $form = $this->submit('web01', [
            'object_type'                       => 'object',
            'imports'                           => [$this->fx('base')],
            'choice' . $this->choice->get('id') => $this->fx('os-linux'),
        ]);

        $this->assertSame('Confirm check command change', $form->getElement('confirm_remove_imports')?->getLabel());
        $host = IcingaHost::load($this->fx('web01'), $this->getDb());
        $this->assertSame([$this->fx('generic'), $this->fx('os-linux')], $host->getImports());
        $this->assertSame('fast', $host->get('vars.' . $this->fx('mode')));
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            // the host goes before its templates, and the choice can require one of them
            if (IcingaHost::exists($this->fx('web01'), $this->getDb())) {
                IcingaHost::load($this->fx('web01'), $this->getDb())->delete();
            }

            if ($this->choice !== null) {
                $this->choice->setMembers([]);
                $this->choice->store();
                $this->choice->delete();
                $this->choice = null;
            }
        }

        $this->removeFixtures();

        foreach (['HTTP_ACCEPT' => $this->acceptHeader, 'REQUEST_METHOD' => $this->requestMethod] as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }

        $_POST = $this->post;
        if ($this->appRequest !== null) {
            $this->testApp()->setRequest($this->appRequest);
        }

        parent::tearDown();
    }

    /**
     * Create a template with an import, a check command and a value of each
     *
     * The template "linux" imports "ssh", which attaches "port", and uses
     * "check-disk", which attaches "warning". "check-ping" attaches nothing.
     *
     * @return void
     */
    protected function createCommandFixtures(): void
    {
        $warning = $this->createFixtureProperty('warning');
        $port = $this->createFixtureProperty('port');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->createFixtureCommand('check-ping', 'object');
        $this->createFixtureHost('ssh', 'template', [], null, [$port]);
        $template = $this->createFixtureHost('linux', 'template', ['ssh'], 'check-disk');
        $this->storeFixtureValue($template, 'warning', '90%', $warning);
        $this->storeFixtureValue($template, 'port', '22', $port);
    }

    /**
     * Post the form of a stored host as an API request, which the form treats as always sent
     *
     * @param string                         $name Unprefixed name of the host or host template
     * @param array<string, string|string[]> $post
     *
     * @return IcingaHostForm
     */
    protected function submit(string $name, array $post): IcingaHostForm
    {
        $db = $this->getDb();
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();
        $request->setPost(array_merge(['object_type' => 'template', 'object_name' => $this->fx($name)], $post));

        // The form asks the application for the request until it is told which one to use
        $this->appRequest ??= $this->testApp()->getRequest();
        $this->testApp()->setRequest($request);

        $form = (new IcingaHostForm())->setApiRequest()->setDb($db);
        $form->setObject(IcingaHost::load($this->fx($name), $db));
        $form->handleRequest();

        return $form;
    }

    protected function testApp(): Test
    {
        /** @var Test $app */
        $app = Icinga::app();

        return $app;
    }
}
