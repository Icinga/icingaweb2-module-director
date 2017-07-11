<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Table\ObjectsTableHost;
use Icinga\Module\Director\Web\Table\TemplatesTable;
use Icinga\Module\Director\Web\Table\TemplateUsageTable;
use ipl\Html\FormattedString;
use ipl\Html\Html;
use ipl\Html\Link;
use ipl\Web\CompatController;
use ipl\Web\Component\UnorderedList;

class HosttemplateController extends CompatController
{
    use DirectorDb;

    public function objectsAction()
    {
        $template = $this->requireTemplate();
        $this
            ->addSingleTab($this->translate('Hosts'))
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('Hosts based on %s'),
                $template->getObjectName()
            )->addBackToUsageLink($template);

        $table = new ObjectsTableHost($this->db());
        $table->setAuth($this->Auth());
        $table->filterTemplate($template, $this->params->get('inheritance', 'direct'));
        $table->renderTo($this);
    }

    public function templatesAction()
    {
        $template = $this->requireTemplate();
        $this
            ->addSingleTab($this->translate('Host Templates'))
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('Host templates based on %s'),
                $template->getObjectName()
            )->addBackToUsageLink($template);

        $table = TemplatesTable::create('host', $this->db());
        $table->filterTemplate($template, $this->params->get('inheritance', 'direct'));
        $table->renderTo($this);
    }

    protected function addBackToUsageLink(IcingaObject $template)
    {
        $this->actions()->add(
            Link::create(
                $this->translate('Back'),
                'director/hosttemplate/usage',
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
            ->addSingleTab($this->translate('Host Template Usage'))
            ->addTitle($this->translate('Template: %s'), $templateName)
            ->setAutorefreshInterval(10);

        $this->actions()->add([
            Link::create(
                $this->translate('Modify'),
                'director/host/edit',
                ['name' => $templateName],
                ['class' => 'icon-edit']
            ),
            Link::create(
                $this->translate('Preview'),
                'director/host/render',
                ['name' => $templateName],
                [
                    'title' => $this->translate('Template rendering preview'),
                    'class' => 'icon-doc-text'
                ]
            ),
            Link::create(
                $this->translate('History'),
                'director/host/history',
                ['name' => $templateName],
                [
                    'title' => $this->translate('Template history'),
                    'class' => 'icon-history'
                ]
            )
        ]);

        $this->content()->addPrintf(
            $this->translate(
                'This is the "%s" Host Template. Based on this, you might want to:'
            ),
            $templateName
        )->add(
            new UnorderedList([
                new FormattedString($this->translate('Create new Service Checks for %s'), [
                    Link::create(
                        $this->translate('specific Hosts'),
                        'director/servicetemplate/addhost',
                        ['name' => $templateName]
                    )
                ]),
                new FormattedString($this->translate('Assign this Template multiple times using %s'), [
                    Link::create(
                        $this->translate('Apply Rules'),
                        'director/service/add',
                        ['apply' => $templateName]
                    )
                ]),
                new FormattedString($this->translate('Create a new %s inheriting from this one'), [
                    Link::create(
                        $this->translate('Template'),
                        'director/servicetemplate/addhost',
                        ['name' => $templateName]
                    )
                ]),
                new FormattedString($this->translate('Make a Service based on this Template member of a %s'), [
                    Link::create(
                        $this->translate('Service Set'),
                        'director/servicetemplate/addtoset',
                        ['name' => $templateName]
                    ),
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
        return IcingaHost::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
