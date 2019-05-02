<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\HtmlDocument;
use Icinga\Module\Director\Forms\ImportCheckForm;
use Icinga\Module\Director\Forms\ImportRunForm;
use Icinga\Module\Director\Objects\ImportSource;
use ipl\Html\Html;
use gipfl\Translation\TranslationHelper;

class ImportSourceDetails extends HtmlDocument
{
    use TranslationHelper;

    protected $source;

    public function __construct(ImportSource $source)
    {
        $this->source = $source;
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    protected function assemble()
    {
        $source = $this->source;
        $description = $source->get('description');
        if (strlen($description)) {
            $this->add(Html::tag('p', null, $description));
        }

        switch ($source->get('import_state')) {
            case 'unknown':
                $this->add(Html::tag(
                    'p',
                    null,
                    $this->translate(
                        "It's currently unknown whether we are in sync with this Import Source."
                        . ' You should either check for changes or trigger a new Import Run.'
                    )
                ));
                break;
            case 'in-sync':
                $this->add(Html::tag('p', null, sprintf(
                    $this->translate(
                        'This Import Source was last found to be in sync at %s.'
                    ),
                    $source->last_attempt
                )));
                // TODO: check whether...
                // - there have been imports since then, differing from former ones
                // - there have been activities since then
                break;
            case 'pending-changes':
                $this->add(Html::tag('p', ['class' => 'warning'], $this->translate(
                    'There are pending changes for this Import Source. You should trigger a new'
                    . ' Import Run.'
                )));
                break;
            case 'failing':
                $this->add(Html::tag('p', ['class' => 'error'], sprintf(
                    $this->translate(
                        'This Import Source failed when last checked at %s: %s'
                    ),
                    $source->last_attempt,
                    $source->last_error_message
                )));
                break;
            default:
                $this->add(Html::tag('p', ['class' => 'error'], sprintf(
                    $this->translate('This Import Source has an invalid state: %s'),
                    $source->get('import_state')
                )));
        }

        $this->add(
            ImportCheckForm::load()
                ->setImportSource($source)
                ->handleRequest()
        );
        $this->add(
            ImportRunForm::load()
                ->setImportSource($source)
                ->handleRequest()
        );
    }
}
