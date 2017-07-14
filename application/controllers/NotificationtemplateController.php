<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaNotification;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Table\ApplyRulesTable;
use Icinga\Module\Director\Web\Table\TemplatesTable;
use Icinga\Module\Director\Web\Table\TemplateUsageTable;
use ipl\Html\FormattedString;
use ipl\Html\Html;
use ipl\Html\Link;
use ipl\Web\CompatController;
use ipl\Web\Component\UnorderedList;

class NotificationtemplateController extends CompatController
{
    use DirectorDb;

    public function templatesAction()
    {
        $template = $this->requireTemplate();
        $this
            ->addSingleTab($this->translate('Notification Templates'))
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('Notification templates based on %s'),
                $template->getObjectName()
            )->addBackToUsageLink($template);

        $table = TemplatesTable::create('notification', $this->db());
        $table->filterTemplate($template, $this->params->get('inheritance', 'direct'));
        $table->renderTo($this);
    }

    public function applyrulesAction()
    {
        $template = $this->requireTemplate();
        $this
            ->addSingleTab($this->translate('Applied Notifications'))
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('Notification Apply Rules based on %s'),
                $template->getObjectName()
            )->addBackToUsageLink($template);

        $table = new ApplyRulesTable($this->db());
        $table->setType('notification');
        $table->filterTemplate($template, $this->params->get('inheritance', 'direct'));
        $table->renderTo($this);
    }

    protected function addBackToUsageLink(IcingaObject $template)
    {
        $this->actions()->add(
            Link::create(
                $this->translate('Back'),
                'director/notificationtemplate/usage',
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
            ->addSingleTab($this->translate('Notification Template Usage'))
            ->addTitle($this->translate('Template: %s'), $templateName)
            ->setAutorefreshInterval(10);

        $this->actions()->add([
            Link::create(
                $this->translate('Modify'),
                'director/notification/edit',
                ['name' => $templateName],
                ['class' => 'icon-edit']
            ),
            Link::create(
                $this->translate('Preview'),
                'director/notification/render',
                ['name' => $templateName],
                [
                    'title' => $this->translate('Template rendering preview'),
                    'class' => 'icon-doc-text'
                ]
            ),
            Link::create(
                $this->translate('History'),
                'director/notification/history',
                ['name' => $templateName],
                [
                    'title' => $this->translate('Template history'),
                    'class' => 'icon-history'
                ]
            )
        ]);

        $this->content()->addPrintf(
            $this->translate(
                'This is the "%s" Notification Template. Based on this, you might want to:'
            ),
            $templateName
        )->add(
            new UnorderedList([
                new FormattedString($this->translate('Assign this Template multiple times using %s'), [
                    Link::create(
                        $this->translate('Apply Rules'),
                        'director/notification/add',
                        [
                            'type' => 'apply',
                            'apply' => $templateName
                        ]
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
        return IcingaNotification::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
