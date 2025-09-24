<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Stdlib\Filter;
use ipl\Web\Filter\QueryString;
use ipl\Web\Layout\MinimalItemLayout;
use ipl\Web\Url;
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
        parent::createListItem($data);

        $item = parent::createListItem($data);
        if ($this->getDetailActionsDisabled()) {
            return $item;
        }

        $url = Url::fromPath('director/host/variables');
        $filter = Filter::equal('name', $data->name);
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
