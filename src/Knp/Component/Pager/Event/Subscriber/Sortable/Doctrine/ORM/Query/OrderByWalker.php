<?php

namespace Knp\Component\Pager\Event\Subscriber\Sortable\Doctrine\ORM\Query;

use Doctrine\ORM\Query\TreeWalkerAdapter,
    Doctrine\ORM\Query\AST\SelectStatement,
    Doctrine\ORM\Query\AST\PathExpression,
    Doctrine\ORM\Query\AST\OrderByItem,
    Doctrine\ORM\Query\AST\OrderByClause;

/**
 * OrderBy Query TreeWalker for Sortable functionality
 * in doctrine paginator
 */
class OrderByWalker extends TreeWalkerAdapter
{
    /**
     * Sort key alias hint name
     */
    const HINT_PAGINATOR_SORT_ALIAS = 'knp_paginator.sort.alias';
    const HINT_PAGINATOR_GROUP_SORT_ALIAS = 'knp_paginator.group_sort.alias';

    /**
     * Sort key field hint name
     */
    const HINT_PAGINATOR_SORT_FIELD = 'knp_paginator.sort.field';
    const HINT_PAGINATOR_GROUP_SORT_FIELD = 'knp_paginator.group_sort.field';

    /**
     * Sort direction hint name
     */
    const HINT_PAGINATOR_SORT_DIRECTION = 'knp_paginator.sort.direction';
    const HINT_PAGINATOR_GROUP_SORT_DIRECTION = 'knp_paginator.group_sort.direction';

    /**
     * Walks down a SelectStatement AST node, modifying it to
     * sort the query like requested by url
     *
     * @param SelectStatement $AST
     * @return void
     */
    public function walkSelectStatement(SelectStatement $AST)
    {
        $query = $this->_getQuery();
        $field = $query->getHint(self::HINT_PAGINATOR_SORT_FIELD);
        $group_field = $query->getHint(self::HINT_PAGINATOR_GROUP_SORT_FIELD);
        $alias = $query->getHint(self::HINT_PAGINATOR_SORT_ALIAS);
        $group_alias = $query->getHint(self::HINT_PAGINATOR_GROUP_SORT_ALIAS);

        $components = $this->_getQueryComponents();
        if ($alias !== false) {
            if (!array_key_exists($alias, $components)) {
                throw new \UnexpectedValueException("There is no component aliased by [{$alias}] in the given Query");
            }
            $meta = $components[$alias];
            if (!$meta['metadata']->hasField($field)) {
                throw new \UnexpectedValueException("There is no such field [{$field}] in the given Query component, aliased by [$alias]");
            }
        } elseif(strlen($field) > 0) {
            if (!array_key_exists($field, $components)) {
                throw new \UnexpectedValueException("There is no component field [{$field}] in the given Query");
            }
        }
        if ($group_alias != '') {
            if (!array_key_exists($group_alias, $components)) {
                throw new \UnexpectedValueException("There is no component aliased by [{$alias}] in the given Query");
            }
            $meta = $components[$group_alias];
            if (!$meta['metadata']->hasField($group_field)) {
                throw new \UnexpectedValueException("There is no such field [{$field}] in the given Query component, aliased by [$alias]");
            }
        } elseif(strlen($group_field) > 0) {
            if (!array_key_exists($group_field, $components)) {
                throw new \UnexpectedValueException("There is no component field [{$field}] in the given Query");
            }
        }

        $direction = $query->getHint(self::HINT_PAGINATOR_SORT_DIRECTION);
        if ($alias !== false) {
            $pathExpression = new PathExpression(PathExpression::TYPE_STATE_FIELD, $alias, $field);
            $pathExpression->type = PathExpression::TYPE_STATE_FIELD;
        } else {
            $pathExpression = $field;
        }

        $group_direction = $query->getHint(self::HINT_PAGINATOR_GROUP_SORT_DIRECTION);
        if ($group_alias !== false) {
            $group_pathExpression = new PathExpression(PathExpression::TYPE_STATE_FIELD, $group_alias, $group_field);
            $group_pathExpression->type = PathExpression::TYPE_STATE_FIELD;
        } elseif(strlen($group_field) > 0) {
            $pathExpression = $group_field;
        }

        $orderByItem = new OrderByItem($pathExpression);
        $orderByItem->type = $direction;
        
        if(strlen($group_field) > 0) {
            $group_orderByItem = new OrderByItem($group_pathExpression);
            $group_orderByItem->type = $group_direction;
        }

        if ($AST->orderByClause) {
            $set = false;
            foreach ($AST->orderByClause->orderByItems as $key => $item) {
                if ($item->expression instanceof PathExpression) {
                    if ($item->expression->identificationVariable === $alias && $item->expression->field === $field) {
                        $item->type = $direction;
                        $set = true;
                    }
                    if (strlen($group_field) > 0 && ($item->expression->identificationVariable === $group_alias && $item->expression->field === $group_field)) {
                        unset($AST->orderByClause->orderByItems[$key]);
                    }
                }
            }
            if (!$set) {
                array_unshift($AST->orderByClause->orderByItems, $orderByItem);
            }
            if (strlen($group_field) > 0) {
                array_unshift($AST->orderByClause->orderByItems, $group_orderByItem);
            }
        } else {
            $clauses = array($orderByItem);
            if(strlen($group_field) > 0)
                array_unshift($clauses, $group_orderByItem);
            $AST->orderByClause = new OrderByClause($clauses);
        }
    }
}
