<?php

namespace Icinga\Module\Director\Web\Controller;

use gipfl\Web\Widget\Hint;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Table\ApplyRulesTable;
use Icinga\Module\Director\Web\Table\ObjectsTable;
use Icinga\Module\Director\Web\Table\TemplatesTable;
use Icinga\Module\Director\Web\Table\TemplateUsageTable;
use Icinga\Module\Director\Web\Tabs\ObjectTabs;
use Icinga\Module\Director\Web\Widget\UnorderedList;
use ipl\Html\FormattedString;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\CompatController;

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
            ->setBaseObjectUrl($this->getBaseObjectUrl())
            ->filterTemplate($template, $this->getInheritance())
            ->renderTo($this);
    }

    public function applyrulesAction()
    {
        $type = $this->getType();
        $template = $this->requireTemplate();
        $this
            ->addSingleTab(sprintf($this->translate('Applied %s'), $this->getTranslatedPluralType()))
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('Notification Apply Rules based on %s'),
                $template->getObjectName()
            )->addBackToUsageLink($template);

        ApplyRulesTable::create($type, $this->db())
            ->setBaseObjectUrl($this->getBaseObjectUrl())
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

        TemplatesTable::create($this->getType(), $this->db())
            ->filterTemplate($template, $this->getInheritance())
            ->renderTo($this);
    }

    protected function getInheritance()
    {
        return $this->params->get('inheritance', 'direct');
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
        if (! $template->isTemplate() && $template instanceof IcingaCommand) {
            $this->redirectNow($this->url()->setPath('director/command'));
        }
        $templateName = $template->getObjectName();

        $type = $this->getType();
        $this->tabs(new ObjectTabs($type, $this->Auth(), $template))->activate('modify');
        $this
            ->addTitle($this->translate('Template: %s'), $templateName)
            ->setAutorefreshInterval(10);

        $this->actions()->add([
            Link::create(
                $this->translate('Modify'),
                "director/$type/edit",
                ['uuid' => $template->getUniqueId()->toString()],
                ['class' => 'icon-edit']
            )
        ]);
        if ($template instanceof ExportInterface) {
            $this->actions()->add(Link::create(
                $this->translate('Add to Basket'),
                'director/basket/add',
                [
                    'type'  => ucfirst($this->getType()) . 'Template',
                    'names' => $template->getUniqueIdentifier()
                ],
                ['class' => 'icon-tag']
            ));
        }

        $list = new UnorderedList([], [
            'class' => 'vertical-action-list'
        ]);

        $auth = $this->Auth();

        if ($type !== 'notification') {
            $list->addItem(new FormattedString(
                $this->translate('Create a new %s inheriting from this template'),
                [Link::create(
                    $this->translate('Object'),
                    "director/$type/add",
                    ['imports' => $templateName, 'type' => 'object']
                )]
            ));
        }
        if ($auth->hasPermission('director/admin')) {
            $list->addItem(new FormattedString(
                $this->translate('Create a new %s inheriting from this one'),
                [Link::create(
                    $this->translate('Template'),
                    "director/$type/add",
                    ['imports' => $templateName, 'type' => 'template']
                )]
            ));
        }
        if ($template->supportsApplyRules()) {
            $list->addItem(new FormattedString(
                $this->translate('Create a new %s inheriting from this template'),
                [Link::create(
                    $this->translate('Apply Rule'),
                    "director/$type/add",
                    ['imports' => $templateName, 'type' => 'apply']
                )]
            ));
        }

        $typeName = $this->getTranslatedType();
        $this->content()->add(Html::sprintf(
            $this->translate(
                'This is the "%s" %s Template. Based on this, you might want to:'
            ),
            $typeName,
            $templateName
        ))->add(
            $list
        )->add(
            Html::tag('h2', null, $this->translate('Current Template Usage'))
        );

        try {
            $this->content()->add(
                TemplateUsageTable::forTemplate($template)
            );
        } catch (NestingError $e) {
            $this->content()->add(Hint::error($e->getMessage()));
        }
    }

    protected function getType()
    {
        return $this->template()->getShortTableName();
    }

    protected function getPluralType()
    {
        return preg_replace(
            '/cys$/',
            'cies',
            $this->template()->getShortTableName() . 's'
        );
    }

    protected function getTranslatedType()
    {
        return $this->translate(ucfirst($this->getType()));
    }

    protected function getTranslatedPluralType()
    {
        return $this->translate(ucfirst($this->getPluralType()));
    }

    protected function getBaseObjectUrl()
    {
        return $this->getType();
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
