<?php

namespace Icinga\Module\Director\Web\ActionBar;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\ActionBar;
use Icinga\Web\Request;

class AutomationObjectActionBar extends ActionBar
{
    use TranslationHelper;

    /** @var Request */
    protected $request;

    protected $label;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function assemble()
    {
        $request = $this->request;
        $action = $request->getActionName();
        $controller = $request->getControllerName();
        $params = ['id' => $request->getParam('id')];
        $links = [
            'index' => Link::create(
                $this->translate('Overview'),
                "director/$controller",
                $params,
                ['class' => 'icon-info']
            ),
            'edit' => Link::create(
                $this->translate('Modify'),
                "director/$controller/edit",
                $params,
                ['class' => 'icon-edit']
            ),
            'clone' => Link::create(
                $this->translate('Clone'),
                "director/$controller/clone",
                $params,
                ['class' => 'icon-paste']
            ),
            /*
            // TODO: enable once handled in the controller
            'export' => Link::create(
                $this->translate('Download JSON'),
                $this->request->getUrl()->with('format', 'json'),
                null,
                [
                    'data-base-target' => '_blank',
                ]
            )
            */

        ];
        unset($links[$action]);
        $this->add($links);
    }
}
