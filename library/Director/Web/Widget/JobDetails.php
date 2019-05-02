<?php

namespace Icinga\Module\Director\Web\Widget;

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
        if ($job->disabled === 'y') {
            $this->add(Html::tag('p', ['class' => 'error'], sprintf(
                $this->translate(
                    'This job would run every %ds. It has been disabled and will'
                    . ' therefore not be executed as scheduled'
                ),
                $job->run_interval
            )));
        } else {
            //$class = $job->job(); echo $class::getDescription()
            $msg = $job->isPending()
                ? sprintf(
                    $this->translate('This job runs every %ds and is currently pending'),
                    $job->run_interval
                )
                : sprintf(
                    $this->translate('This job runs every %ds.'),
                    $job->run_interval
                );
            $this->add(Html::tag('p', null, $msg));
        }

        if ($job->ts_last_attempt) {
            if ($job->last_attempt_succeeded) {
                $this->add(Html::tag('p', null, sprintf(
                    $this->translate('The last attempt succeeded at %s'),
                    $job->ts_last_attempt
                )));
            } else {
                $this->add(Html::tag('p', ['class' => 'error'], sprintf(
                    $this->translate('The last attempt failed at %s: %s'),
                    $job->ts_last_attempt,
                    $job->ts_last_error
                )));
            }
        } else {
            $this->add(Html::tag('p', null, $this->translate('This job has not been executed yet')));
        }
    }
}
