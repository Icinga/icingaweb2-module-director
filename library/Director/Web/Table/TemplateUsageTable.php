<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use gipfl\IcingaWeb2\Link;
use ipl\Html\Table;
use gipfl\Translation\TranslationHelper;

class TemplateUsageTable extends Table
{
    use TranslationHelper;

    use TableWithBranchSupport;

    protected $defaultAttributes = ['class' => 'pivot'];

    protected $objectType;

    protected $searchColumns = [];

    public function getTypes()
    {
        return [
            'templates'  => $this->translate('Templates'),
            'objects'    => $this->translate('Objects'),
        ];
    }

    /**
     * @param IcingaObject $template
     * @param Branch|null $branch
     *
     * @return TemplateUsageTable
     *
     * @throws ProgrammingError
     */
    public static function forTemplate(IcingaObject $template, Branch $branch = null)
    {
        $type = ucfirst($template->getShortTableName());
        $class = __NAMESPACE__ . "\\{$type}TemplateUsageTable";
        if (class_exists($class)) {
            return new $class($template, $branch);
        } else {
            return new static($template, $branch);
        }
    }

    public function getColumnsToBeRendered()
    {
        return [
            '',
            $this->translate('Direct'),
            $this->translate('Indirect'),
            $this->translate('Total')
        ];
    }

    protected function __construct(IcingaObject $template, Branch $branch = null)
    {

        if ($template->get('object_type') !== 'template') {
            throw new ProgrammingError(
                'TemplateUsageTable expects a template, got %s',
                $template->get('object_type')
            );
        }

        $this->setBranch($branch);
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
                            "director/{$objectType}template/$type",
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
            $this->getHeader()->add(Table::row($this->getColumnsToBeRendered(), null, 'th'));
            $this->add($rows);
        } else {
            $this->add($this->translate('This template is not in use'));
        }
    }

    protected function getUsageSummary(IcingaObject $template)
    {
        $connection = $template->getConnection();
        $db = $connection->getDbAdapter();

        $types = array_keys($this->getTypes());
        $direct = [];
        $indirect = [];
        $templateType = $template->getShortTableName();

        foreach ($this->getSummaryTables($templateType, $connection) as $type => $summaryTable) {
            $directTable = clone $summaryTable;
            $inDirectTable = clone $summaryTable;

            $direct[$type] = $db->query(
                $directTable
                    ->filterTemplate($template, IcingaObjectFilterHelper::INHERIT_DIRECT)
                    ->getQuery()
            )->rowCount();
            $indirect[$type] = $db->query(
                $inDirectTable
                    ->filterTemplate($template, IcingaObjectFilterHelper::INHERIT_INDIRECT)
                    ->getQuery()
            )->rowCount();
        }

        $total = [];
        foreach ($types as $type) {
            $total[$type] = $direct[$type] + $indirect[$type];
        }

        return (object) [
            'direct'   => (object) $direct,
            'indirect' => (object) $indirect,
            'total'    => (object) $total
        ];
    }

    protected function getSummaryTables(string $templateType, Db $connection)
    {
        return [
            'templates' => TemplatesTable::create(
                $templateType,
                $connection
            ),
            'objects'   => ObjectsTable::create($templateType, $connection)
                ->setAuth(Auth::getInstance())
                ->setBranchUuid($this->branchUuid)
        ];
    }
}
