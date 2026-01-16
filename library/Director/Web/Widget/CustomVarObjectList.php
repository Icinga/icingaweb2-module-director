<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\HtmlElement;
use ipl\Stdlib\Filter;
use ipl\Web\Filter\QueryString;
use ipl\Web\Layout\MinimalItemLayout;
use ipl\Web\Url;
use ipl\Web\Widget\EmptyStateBar;
use ipl\Web\Widget\ItemList;
use ipl\Web\Widget\ListItem as ListItem;

class CustomVarObjectList extends ItemList
{
    protected bool $actionDisabled = true;

    public function __construct($data)
    {
        parent::__construct($data, new CustomVarRenderer());

        $this->setItemLayoutClass(MinimalItemLayout::class);
    }

    public function getDetailActionsDisabled(): bool
    {
        return $this->actionDisabled;
    }

    public function setDetailActionsDisabled(bool $actionDisabled = true): static
    {
        $this->actionDisabled = $actionDisabled;

        return $this;
    }

    protected function createListItem(object $data): ListItem
    {
        $item = parent::createListItem($data);
        if ($this->getDetailActionsDisabled()) {
            return $item;
        }

        $objectInstance = $data->object_class;
        if ($data->object_class === 'service' && $data->host_name !== null) {
            $filter = Filter::all(
                Filter::equal('name', $data->name),
                Filter::equal('host_name', $data->host_name)
            );
        } else {
            $filter = Filter::equal('name', $data->name);
        }

        $url = Url::fromPath("director/$objectInstance/variables");
        $this->getAttributes()->add('class', 'action-list');
        $this->getAttributes()
             ->registerAttributeCallback('data-icinga-detail-url', function () use ($url) {
                 return $this->getDetailActionsDisabled() ? null : (string) $url;
             });

        $item->getAttributes()
               ->registerAttributeCallback('data-action-item', function () {
                   return ! $this->getDetailActionsDisabled();
               })
               ->registerAttributeCallback('data-icinga-detail-filter', function () use ($filter) {
                   return $this->getDetailActionsDisabled() ? null : QueryString::render($filter);
               });

        return $item;
    }
}
