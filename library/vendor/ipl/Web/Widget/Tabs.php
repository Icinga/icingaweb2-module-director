<?php

namespace dipl\Web\Widget;

use Exception;
use Icinga\Web\Widget\Tabs as WebTabs;
use InvalidArgumentException;
use dipl\Html\ValidHtml;

class Tabs extends WebTabs implements ValidHtml
{
    /**
     * @param string $name
     * @return $this
     */
    public function activate($name)
    {
        try {
            parent::activate($name);
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                "Can't activate '$name', there is no such tab"
            );
        }

        return $this;
    }

    /**
     * @param string $name
     * @param array|\Icinga\Web\Widget\Tab $tab
     * @return $this
     */
    public function add($name, $tab)
    {
        try {
            parent::add($name, $tab);
        } catch (Exception $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        return $this;
    }
}
