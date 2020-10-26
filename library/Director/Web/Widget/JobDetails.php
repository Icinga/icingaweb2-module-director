<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\Web\Widget\Hint;
use Icinga\Date\DateFormatter;
use ipl\Html\HtmlDocument;
use Icinga\Module\Director\Objects\DirectorJob;
use ipl\Html\Html;
use gipfl\Translation\TranslationHelper;

class JobDetails extends HtmlDocument
{
    use TranslationHelper;

    /**
     * JobDetails constructor.
     * @param DirectorJob $job
     * @throws \Icinga\Exception\NotFoundError
     */
    public function __construct(DirectorJob $job)
    {
        $runInterval = $job->get('run_interval');
        if ($job->hasBeenDisabled()) {
            $this->add(Hint::error(sprintf(
                $this->translate(
                    'This job would run every %ds. It has been disabled and will'
                    . ' therefore not be executed as scheduled'
                ),
                $runInterval
            )));
        } else {
            //$class = $job->job(); echo $class::getDescription()
            $msg = $job->isPending()
                ? sprintf(
                    $this->translate('This job runs every %ds and is currently pending'),
                    $runInterval
                )
                : sprintf(
                    $this->translate('This job runs every %ds.'),
                    $runInterval
                );
            $this->add(Html::tag('p', null, $msg));
        }

        $tsLastAttempt = $job->get('ts_last_attempt');
        $ts = \strtotime($tsLastAttempt);
        $timeAgo = Html::tag('span', [
            'class' => 'time-ago',
            'title' => DateFormatter::formatDateTime($ts)
        ], DateFormatter::timeAgo($ts));
        if ($tsLastAttempt) {
            if ($job->get('last_attempt_succeeded') === 'y') {
                $this->add(Hint::ok(Html::sprintf(
                    $this->translate('The last attempt succeeded %s'),
                    $timeAgo
                )));
            } else {
                $this->add(Hint::error(Html::sprintf(
                    $this->translate('The last attempt failed %s: %s'),
                    $timeAgo,
                    $job->get('last_error_message')
                )));
            }
        } else {
            $this->add(Hint::warning($this->translate('This job has not been executed yet')));
        }
    }
}
