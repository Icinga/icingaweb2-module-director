<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\Translation\TranslationHelper;
use Icinga\Date\DateFormatter;
use Icinga\Module\Director\Daemon\RunningDaemonInfo;
use Icinga\Util\Format;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\Table;

class BackgroundDaemonDetails extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    /** @var RunningDaemonInfo */
    protected $info;

    /** @var \stdClass TODO: get rid of this */
    protected $daemon;

    public function __construct(RunningDaemonInfo $info, $daemon)
    {
        $this->info = $info;
        $this->daemon = $daemon;
    }

    protected function assemble()
    {
        $info = $this->info;
        if ($info->hasBeenStopped()) {
            $this->add(Html::tag('p', [
                'class' => 'state-hint error'
            ], Html::sprintf(
                $this->translate(
                    'Daemon has been stopped %s, was running with PID %s as %s@%s'
                ),
                // $info->getHexUuid(),
                $this->timeAgo($info->getTimestampStopped() / 1000),
                Html::tag('strong', (string) $info->getPid()),
                Html::tag('strong', $info->getUsername()),
                Html::tag('strong', $info->getFqdn())
            )));
        } elseif ($info->isOutdated()) {
            $this->add(Html::tag('p', [
                'class' => 'state-hint error'
            ], Html::sprintf(
                $this->translate(
                    'Daemon keep-alive is outdated, was last seen running with PID %s as %s@%s %s'
                ),
                // $info->getHexUuid(),
                Html::tag('strong', (string) $info->getPid()),
                Html::tag('strong', $info->getUsername()),
                Html::tag('strong', $info->getFqdn()),
                $this->timeAgo($info->getLastUpdate() / 1000)
            )));
        } else {
            $details = new NameValueTable();
            $details->addNameValuePairs([
                $this->translate('Startup Time') => DateFormatter::formatDateTime($info->getTimestampStarted() / 1000),
                $this->translate('PID') => $info->getPid(),
                $this->translate('Username') => $info->getUsername(),
                $this->translate('FQDN') => $info->getFqdn(),
                $this->translate('Running with systemd') => $info->isRunningWithSystemd()
                    ? $this->translate('yes')
                    : $this->translate('no'),
                $this->translate('Binary') => $info->getBinaryPath()
                    . ($info->binaryRealpathDiffers() ? ' -> ' . $info->getBinaryRealpath() : ''),
                $this->translate('PHP Binary') => $info->getPhpBinaryPath()
                    . ($info->phpBinaryRealpathDiffers() ? ' -> ' . $info->getPhpBinaryRealpath() : ''),
                $this->translate('PHP Version') => $info->getPhpVersion(),
                $this->translate('PHP Integer') => $info->has64bitIntegers()
                    ? '64bit'
                    : Html::sprintf(
                        '%sbit (%s)',
                        $info->getPhpIntegerSize() * 8,
                        Html::tag('span', ['class' => 'error'], $this->translate('unsupported'))
                    ),
            ]);
            $this->add($details);
            $this->add(Html::tag('p', [
                'class' => 'state-hint ok'
            ], Html::sprintf(
                $this->translate(
                    'Daemon is running with PID %s as %s@%s, last refresh happened %s'
                ),
                // $info->getHexUuid(),
                Html::tag('strong', (string)$info->getPid()),
                Html::tag('strong', $info->getUsername()),
                Html::tag('strong', $info->getFqdn()),
                $this->timeAgo($info->getLastUpdate() / 1000)
            )));

            $this->add(Html::tag('h2', $this->translate('Process List')));
            $processes = \json_decode($this->daemon->process_info);
            $table = new Table();
            $table->add(Html::tag('thead', Html::tag('tr', Html::wrapEach([
                'PID',
                'Command',
                'Memory'
            ], 'th'))));
            $table->setAttribute('class', 'common-table');
            foreach ($processes as $pid => $process) {
                $table->add($table::row([
                    [
                        Icon::create($process->running ? 'ok' : 'warning-empty'),
                        ' ',
                        $pid
                    ],
                    Html::tag('pre', $process->command),
                    Format::bytes($process->memory->rss)
                ]));
            }
            $this->add($table);
        }
    }

    protected function timeAgo($time)
    {
        return Html::tag('span', [
            'class' => 'time-ago',
            'title' => DateFormatter::formatDateTime($time)
        ], DateFormatter::timeAgo($time));
    }
}
