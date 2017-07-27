<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Resolver\TemplateTree;
use ipl\Html\Link;
use ipl\Html\Table;
use ipl\Translation\TranslationHelper;

class TemplateUsageTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = ['class' => 'pivot'];

    protected $objectType;

    public function getTypes()
    {
        return [
            'templates'  => $this->translate('Templates'),
            'objects'    => $this->translate('Objects'),
        ];
    }

    protected function getTypeSummaryDefinitions()
    {
        return [
            'templates'  => $this->getSummaryLine('template'),
            'objects'    => $this->getSummaryLine('object'),
        ];
    }

    /**
     * @param IcingaObject $template
     * @return TemplateUsageTable
     */
    public static function forTemplate(IcingaObject $template)
    {
        $type = ucfirst($template->getShortTableName());
        $class = __NAMESPACE__ . "\\${type}TemplateUsageTable";
        if (class_exists($class)) {
            return new $class($template);
        } else {
            return new static($template);
        }
    }

    protected function __construct(IcingaObject $template)
    {
        $this->setColumnsToBeRendered([
            '',
            $this->translate('Direct'),
            $this->translate('Indirect'),
            $this->translate('Total')
        ]);

        if ($template->get('object_type') !== 'template') {
            throw new ProgrammingError(
                'TemplateUsageTable expects a template, got %s',
                $template->get('object_type')
            );
        }

        $this->objectType = $objectType = $template->getShortTableName();
        $types = $this->getTypes();
        $usage = $this->getUsageSummary($template);

        $used = false;
        $rows = [];
        foreach ($types as $type => $typeTitle) {
            $tr = Table::tr(Table::th($typeTitle));
            foreach (['direct', 'indirect', 'total'] as $inheritance) {
                $count = $usage->$inheritance->$type;
                if (! $used && $count > 0) {
                    $used = true;
                }
                $tr->add(
                    Table::td(
                        Link::create(
                            $count,
                            "director/${objectType}template/$type",
                            [
                                'name' => $template->getObjectName(),
                                'inheritance' => $inheritance
                            ]
                        )
                    )
                );
            }
            $rows[] = $tr;
        }

        if ($used) {
            $this->header();
            $this->body()->add($rows);
        } else {
            $this->body()->add($this->translate('This template is not in use'));
        }
    }

    protected function getUsageSummary(IcingaObject $template)
    {
        $id = $template->getAutoincId();
        $connection = $template->getConnection();
        $db = $connection->getDbAdapter();
        $oType = $this->objectType;
        $tree = new TemplateTree($oType, $connection);
        $ids = $tree->listDescendantIdsFor($template);
        if (empty($ids)) {
            $ids = [0];
        }

        $baseQuery = $db->select()->from(
            ['o' => 'icinga_' . $oType],
            $this->getTypeSummaryDefinitions()
        )->joinLeft(
            ['oi' => "icinga_${oType}_inheritance"],
            "oi.${oType}_id = o.id",
            []
        );

        $query = clone($baseQuery);
        $direct = $db->fetchRow(
            $query->where("oi.parent_${oType}_id = ?", $id)
        );
        $query = clone($baseQuery);
        $indirect = $db->fetchRow(
            $query->where("oi.parent_${oType}_id IN (?)", $ids)
        );
        //$indirect->templates = count($ids) - 1;
        $total = [];
        $types = array_keys($this->getTypes());
        foreach ($types as $type) {
            $total[$type] = $direct->$type + $indirect->$type;
        }

        return (object) [
            'direct'   => $direct,
            'indirect' => $indirect,
            'total'    => (object) $total
        ];
    }

    protected function getSummaryLine($type, $extra = null)
    {
        if ($extra !== null) {
            $extra = " AND $extra";
        }
        return "COALESCE(SUM(CASE WHEN o.object_type = '${type}'${extra} THEN 1 ELSE 0 END), 0)";
    }
}
