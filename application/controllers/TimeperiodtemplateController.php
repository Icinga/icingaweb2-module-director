<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Table\ObjectsTable;
use Icinga\Module\Director\Web\Table\TemplatesTable;
use Icinga\Module\Director\Web\Table\TemplateUsageTable;
use ipl\Html\FormattedString;
use ipl\Html\Html;
use ipl\Html\Link;
use ipl\Web\CompatController;
use ipl\Web\Widget\UnorderedList;

class TimeperiodtemplateController extends CompatController
{
    use DirectorDb;

    public function objectsAction()
    {
        $template = $this->requireTemplate();
        $type = $template->getShortTableName();
        $this
            ->addSingleTab($this->translate('Timeperiods'))
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('Timeperiods based on %s'),
                $template->getObjectName()
            )->addBackToUsageLink($template);

        ObjectsTable::create($type, $this->db())
            ->setAuth($this->Auth())
            ->filterTemplate($template, $this->params->get('inheritance', 'direct'))
            ->renderTo($this);
    }

    public function templatesAction()
    {
        $template = $this->requireTemplate();
        $type = $template->getShortTableName();
        $this
            ->addSingleTab($this->translate('Timeperiod Templates'))
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('Timeperiod templates based on %s'),
                $template->getObjectName()
            )->addBackToUsageLink($template);

        $table = TemplatesTable::create($type, $this->db());
        $table->filterTemplate($template, $this->params->get('inheritance', 'direct'));
        $table->renderTo($this);
    }

    protected function addBackToUsageLink(IcingaObject $template)
    {
        $this->actions()->add(
            Link::create(
                $this->translate('Back'),
                'director/timeperiodtemplate/usage',
                ['name' => $template->getObjectName()],
                ['class' => 'icon-left-big']
            )
        );

        return $this;
    }

    public function usageAction()
    {
        $template = $this->requireTemplate();
        $templateName = $template->getObjectName();

        $this
            ->addSingleTab($this->translate('Timeperiod Template Usage'))
            ->addTitle($this->translate('Template: %s'), $templateName)
            ->setAutorefreshInterval(10);

        $type = $template->getShortTableName();
        $this->actions()->add([
            Link::create(
                $this->translate('Modify'),
                "director/$type/edit",
                ['name' => $templateName],
                ['class' => 'icon-edit']
            ),
            Link::create(
                $this->translate('Preview'),
                "director/$type/render",
                ['name' => $templateName],
                [
                    'title' => $this->translate('Template rendering preview'),
                    'class' => 'icon-doc-text'
                ]
            ),
            Link::create(
                $this->translate('History'),
                "director/$type/history",
                ['name' => $templateName],
                [
                    'title' => $this->translate('Template history'),
                    'class' => 'icon-history'
                ]
            )
        ]);

        $this->content()->addPrintf(
            $this->translate(
                'This is the "%s" Timeperiod Template. Based on this, you might want to:'
            ),
            $templateName
        )->add(
            new UnorderedList([
                new FormattedString($this->translate('Create a new %s inheriting from this one'), [
                    Link::create(
                        $this->translate('Object'),
                        'director/timeperiod/add',
                        ['import' => $templateName]
                    )
                ]),
                new FormattedString($this->translate('Create a new %s inheriting from this one'), [
                    Link::create(
                        $this->translate('Template'),
                        'director/timeperiod/add',
                        ['import' => $templateName, 'type' => 'template']
                    )
                ])
            ], [
                'class' => 'vertical-action-list'
            ])
        )->add(
            Html::tag('h2', null, $this->translate('Current Template Usage'))
        );

        $this->content()->add(
            TemplateUsageTable::forTemplate($template)
        );
    }

    protected function requireTemplate()
    {
        return IcingaTimePeriod::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
