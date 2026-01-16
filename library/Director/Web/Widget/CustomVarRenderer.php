<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\Attributes;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Web\Common\ItemRenderer;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

class CustomVarRenderer implements ItemRenderer
{
    public function assembleAttributes($item, Attributes $attributes, string $layout): void
    {
    }

    public function assembleVisual($item, HtmlDocument $visual, string $layout): void
    {
    }

    public function assembleCaption($item, HtmlDocument $caption, string $layout): void
    {
    }

    public function assembleFooter($item, HtmlDocument $footer, string $layout): void
    {
    }

    public function assembleTitle($item, HtmlDocument $title, string $layout): void
    {
        $title->addHtml(Html::sprintf(
            '%s',
            $this->createSubject($item, $layout),
        ));
    }

    protected function createSubject($item, string $layout): Link
    {
        $objectClass = $item->object_class;
        if ($objectClass === 'service' && $item->host_name !== null) {
            $params = ['name' => $item->name, 'host_name' => $item->host_name];
        } else {
            $params = ['name' => $item->name];
        }

        return new Link(
            $item->name,
            Url::fromPath("director/$objectClass/variables", $params)->getAbsoluteUrl(),
            ['class' => ['subject', 'object-link']]
        );
    }

    public function assembleExtendedInfo($item, HtmlDocument $info, string $layout): void
    {
        $info->addHtml(new HtmlElement('span', null, new Text($item->type)));
    }

    public function assemble($item, string $name, HtmlDocument $element, string $layout): bool
    {
        return false;
    }
}
