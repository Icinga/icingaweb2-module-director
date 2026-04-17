<?php

namespace Icinga\Module\Director\Db;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Resolver\TemplateTree;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Zend_Db_Select as ZfSelect;

class IcingaObjectFilterHelper
{
    public const INHERIT_DIRECT = 'direct';
    public const INHERIT_INDIRECT = 'indirect';
    public const INHERIT_DIRECT_OR_INDIRECT = 'total';

    /**
     * @param IcingaObject|int|string $id
     * @return int
     */
    public static function wantId($id)
    {
        if (is_int($id)) {
            return $id;
        } elseif ($id instanceof IcingaObject) {
            return (int) $id->get('id');
        } elseif (is_string($id) && ctype_digit($id)) {
            return (int) $id;
        } else {
            throw new InvalidArgumentException(sprintf(
                'Numeric ID or IcingaObject expected, got %s',
                // TODO: just type/class info?
                var_export($id, true)
            ));
        }
    }

    /**
     * @param ZfSelect $query
     * @param IcingaObject|int|string $template
     * @param string $tableAlias
     * @param string $inheritanceType
     * @return ZfSelect
     */
    public static function filterByTemplate(
        ZfSelect $query,
        $template,
        $tableAlias = 'o',
        $inheritanceType = self::INHERIT_DIRECT,
        ?UuidInterface $branchuuid = null
    ) {
        $i = $tableAlias . 'i';
        $o = $tableAlias;
        $type = $template->getShortTableName();
        $db = $template->getDb();
        $id = static::wantId($template);

        if ($branchuuid) {
            if ($inheritanceType === self::INHERIT_DIRECT) {
                return $query->where('imports LIKE \'%"' . $template->getObjectName() . '"%\'');
            } elseif (
                $inheritanceType === self::INHERIT_INDIRECT
                || $inheritanceType === self::INHERIT_DIRECT_OR_INDIRECT
            ) {
                $tree = new TemplateTree($type, $template->getConnection());
                $templateNames = $tree->getDescendantsFor($template);

                if ($inheritanceType === self::INHERIT_DIRECT_OR_INDIRECT) {
                    $templateNames[] = $template->getObjectName();
                }

                if (empty($templateNames)) {
                    $condition = '(1 = 0)';
                } else {
                    $condition = 'imports LIKE \'%"' . array_pop($templateNames) . '"%\'';

                    foreach ($templateNames as $templateName) {
                        $condition .= " OR imports LIKE '%\"$templateName\"%'";
                    }
                }

                return $query->where($condition);
            }
        }

        $sub = $db->select()->from(
            array($i => "icinga_{$type}_inheritance"),
            array('e' => '(1)')
        )->where("$i.{$type}_id = $o.id");

        if ($inheritanceType === self::INHERIT_DIRECT) {
            $sub->where("$i.parent_{$type}_id = ?", $id);
        } elseif (
            $inheritanceType === self::INHERIT_INDIRECT
            || $inheritanceType === self::INHERIT_DIRECT_OR_INDIRECT
        ) {
            $tree = new TemplateTree($type, $template->getConnection());
            $ids = $tree->listDescendantIdsFor($template);
            if ($inheritanceType === self::INHERIT_DIRECT_OR_INDIRECT) {
                $ids[] = $template->getAutoincId();
            }

            if (empty($ids)) {
                $sub->where('(1 = 0)');
            } else {
                $sub->where("$i.parent_{$type}_id IN (?)", $ids);
            }
        } else {
            throw new RuntimeException(sprintf(
                'Unable to understand "%s" inheritance',
                $inheritanceType
            ));
        }

        return $query->where('EXISTS ?', $sub);
    }

    public static function filterByHostgroups(
        ZfSelect $query,
        $type,
        $groups,
        $tableAlias = 'o'
    ) {
        if (empty($groups)) {
            // Asked for an empty set of groups? Give no result
            $query->where('(1 = 0)');
        } else {
            $sub = $query->getAdapter()->select()->from(
                array('go' => "icinga_{$type}group_{$type}"),
                array('e' => '(1)')
            )->join(
                array('g' => "icinga_{$type}group"),
                "go.{$type}group_id = g.id"
            )->where("go.{$type}_id = {$tableAlias}.id")
                ->where('g.object_name IN (?)', $groups);

            $query->where('EXISTS ?', $sub);
        }
    }

    public static function filterByResolvedHostgroups(
        ZfSelect $query,
        $type,
        $groups,
        $tableAlias = 'o'
    ) {
        if (empty($groups)) {
            // Asked for an empty set of groups? Give no result
            $query->where('(1 = 0)');
        } else {
            $sub = $query->getAdapter()->select()->from(
                array('go' => "icinga_{$type}group_{$type}_resolved"),
                array('e' => '(1)')
            )->join(
                array('g' => "icinga_{$type}group"),
                "go.{$type}group_id = g.id",
                []
            )->where("go.{$type}_id = {$tableAlias}.id")
                ->where('g.object_name IN (?)', $groups);

            $query->where('EXISTS ?', $sub);
        }
    }
}
