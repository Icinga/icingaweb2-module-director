<?php

namespace Icinga\Module\Director\Web\Widget;

use Icinga\Module\Director\Objects\DirectorJob;
use ipl\Html\Html;
use ipl\Translation\TranslationHelper;

class JobDetails extends Html
{
    use TranslationHelper;

    public function __construct(DirectorJob $job)
    {
        if ($job->disabled === 'y') {
            $this->add(Html::p(['class' => 'error'], sprintf(
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
            $this->add(Html::p($msg));
        }

        if ($job->ts_last_attempt) {
            if ($job->last_attempt_succeeded) {
                $this->add(Html::p(sprintf(
                    $this->translate('The last attempt succeeded at %s'),
                    $job->ts_last_attempt
                )));
            } else {
                $this->add(Html::p(['class' => 'error'], sprintf(
                    $this->translate('The last attempt failed at %s: %s'),
                    $job->ts_last_attempt,
                    $job->ts_last_error
                )));
            }
        } else {
            $this->add(Html::p($this->translate('This job has not been executed yet')));
        }
    }
}
