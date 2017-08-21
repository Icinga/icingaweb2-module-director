<?php

namespace Icinga\Module\Director\Repository;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Resolver\TemplateTree;

class IcingaTemplateRepository
{
    use RepositoryByObjectHelper;

    /** @var TemplateTree */
    protected $tree;

    protected $loadedById = [];

    /**
     * @return TemplateTree
     */
    public function tree()
    {
        if ($this->tree === null) {
            $this->tree = new TemplateTree($this->type, $this->connection);
        }

        return $this->tree;
    }

    /**
     * @param IcingaObject $object
     * @return IcingaObject[]
     */
    public function getTemplatesFor(IcingaObject $object)
    {
        $ids = $this->tree()->listAncestorIdsFor($object);
        $templates = [];
        foreach ($ids as $id) {
            if (! array_key_exists($id, $this->loadedById)) {
                // TODO: load only missing ones at once
                $this->loadedById[$id] = $object::loadWithAutoIncId(
                    $id,
                    $this->connection
                );
            }

            $templates[$id] = $this->loadedById[$id];
        }

        return $templates;
    }

    /**
     * @param IcingaObject $object
     * @return IcingaObject[]
     */
    public function getTemplatesIndexedByNameFor(IcingaObject $object)
    {
        $templates = [];
        foreach ($this->getTemplatesFor($object) as $template) {
            $templates[$template->getObjectName()] = $template;
        }

        return $templates;
    }

    public function persistImportNames()
    {
    }

    public function storeChances(Db $db)
    {
    }
}
