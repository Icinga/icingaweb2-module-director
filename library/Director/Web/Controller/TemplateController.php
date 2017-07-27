<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Table\ObjectsTable;
use Icinga\Module\Director\Web\Table\TemplatesTable;
use Icinga\Module\Director\Web\Table\TemplateUsageTable;
use ipl\Html\FormattedString;
use ipl\Html\Html;
use ipl\Html\Link;
use ipl\Web\CompatController;
use ipl\Web\Widget\UnorderedList;

abstract class TemplateController extends CompatController
{
    use DirectorDb;

    /** @var IcingaObject */
    protected $template;

    public function objectsAction()
    {
        $template = $this->requireTemplate();
        $plural = $this->getTranslatedPluralType();
        $this
            ->addSingleTab($plural)
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('%s based on %s'),
                $plural,
                $template->getObjectName()
            )->addBackToUsageLink($template);

        ObjectsTable::create($this->getType(), $this->db())
            ->setAuth($this->Auth())
            ->filterTemplate($template, $this->params->get('inheritance', 'direct'))
            ->renderTo($this);
    }

    public function templatesAction()
    {
        $template = $this->requireTemplate();
        $typeName = $this->getTranslatedType();
        $this
            ->addSingleTab(sprintf($this->translate('%s Templates'), $typeName))
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('%s templates based on %s'),
                $typeName,
                $template->getObjectName()
            )->addBackToUsageLink($template);

        $table = TemplatesTable::create($this->getType(), $this->db());
        $table->filterTemplate($template, $this->params->get('inheritance', 'direct'));
        $table->renderTo($this);
    }

    protected function addBackToUsageLink(IcingaObject $template)
    {
        $type = $this->getType();
        $this->actions()->add(
            Link::create(
                $this->translate('Back'),
                "director/${type}template/usage",
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
            ->addSingleTab($this->translate('Template Usage'))
            ->addTitle($this->translate('Template: %s'), $templateName)
            ->setAutorefreshInterval(10);

        $type = $this->getType();
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

        $typeName = $this->getTranslatedType();
        $this->content()->addPrintf(
            $this->translate(
                'This is the "%s" %s Template. Based on this, you might want to:'
            ),
            $typeName,
            $templateName
        )->add(
            new UnorderedList([
                new FormattedString($this->translate('Create a new %s inheriting from this one'), [
                    Link::create(
                        $this->translate('Object'),
                        "director/$type/add",
                        ['import' => $templateName]
                    )
                ]),
                new FormattedString($this->translate('Create a new %s inheriting from this one'), [
                    Link::create(
                        $this->translate('Template'),
                        "director/$type/add",
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

    protected function getType()
    {
        return $this->template()->getShortTableName();
    }

    protected function getPluralType()
    {
        return $this->template()->getShortTableName() . 's';
    }

    protected function getTranslatedType()
    {
        return $this->translate(ucfirst($this->getType()));
    }

    protected function getTranslatedPluralType()
    {
        return $this->translate(ucfirst($this->getPluralType()));
    }

    /**
     * @return IcingaObject
     */
    protected function template()
    {
        if ($this->template === null) {
            $this->template = $this->requireTemplate();
        }

        return $this->template;
    }

    /**
     * @return IcingaObject
     */
    abstract protected function requireTemplate();
}
