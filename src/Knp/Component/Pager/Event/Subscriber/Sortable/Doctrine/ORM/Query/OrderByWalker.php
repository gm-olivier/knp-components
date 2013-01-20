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
    const HINT_PAGINATOR_GROUP_ORDER_SORT_ALIAS = 'knp_paginator.group_order_sort.alias';

    /**
     * Sort key field hint name
     */
    const HINT_PAGINATOR_SORT_FIELD = 'knp_paginator.sort.field';
    const HINT_PAGINATOR_GROUP_SORT_FIELD = 'knp_paginator.group_sort.field';
    const HINT_PAGINATOR_GROUP_ORDER_SORT_FIELD = 'knp_paginator.group_order_sort.field';

    /**
     * Sort direction hint name
     */
    const HINT_PAGINATOR_SORT_DIRECTION = 'knp_paginator.sort.direction';
    const HINT_PAGINATOR_GROUP_SORT_DIRECTION = 'knp_paginator.group_sort.direction';
    const HINT_PAGINATOR_GROUP_ORDER_SORT_DIRECTION = 'knp_paginator.group_order_sort.direction';

	protected function checkParams( $components, $alias, $field, $enabled )
	{
        if ($alias !== false) {
            if (!array_key_exists($alias, $components)) {
                throw new \UnexpectedValueException("There is no component aliased by [{$alias}] in the given Query");
            }
            $meta = $components[$alias];
            if (!$meta['metadata']->hasField($field)) {
                throw new \UnexpectedValueException("There is no such field [{$field}] in the given Query component, aliased by [$alias]");
            }
        } elseif($enabled) {
            if (!array_key_exists($field, $components)) {
                throw new \UnexpectedValueException("There is no component field [{$field}] in the given Query");
            }
        }
	}
	
	protected function createPathExpression( $alias, $field, $enabled )
	{
        $pathExpression = null;
        if ($alias !== false) {
            $pathExpression = new PathExpression(PathExpression::TYPE_STATE_FIELD, $alias, $field);
            $pathExpression->type = PathExpression::TYPE_STATE_FIELD;
        } elseif($enabled) {
            $pathExpression = $field;
        }
        
        return $pathExpression;
	}

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
        $alias = $query->getHint(self::HINT_PAGINATOR_SORT_ALIAS);
        $direction = $query->getHint(self::HINT_PAGINATOR_SORT_DIRECTION);
        $group_field = $query->getHint(self::HINT_PAGINATOR_GROUP_SORT_FIELD);
        $group_alias = $query->getHint(self::HINT_PAGINATOR_GROUP_SORT_ALIAS);
        $group_direction = $query->getHint(self::HINT_PAGINATOR_GROUP_SORT_DIRECTION);
        $group_order_field = $query->getHint(self::HINT_PAGINATOR_GROUP_ORDER_SORT_FIELD);
        $group_order_alias = $query->getHint(self::HINT_PAGINATOR_GROUP_ORDER_SORT_ALIAS);
        $group_order_direction = $query->getHint(self::HINT_PAGINATOR_GROUP_ORDER_SORT_DIRECTION);

		$sorted = ( $field !== false );
		$grouped = ( $group_field !== false );

        $components = $this->_getQueryComponents();
        $this->checkParams( $components, $alias, $field, $sorted );
        $this->checkParams( $components, $group_alias, $group_field, $grouped );
        $this->checkParams( $components, $group_order_alias, $group_order_field, $grouped );

		$pathExpression = $this->createPathExpression( $alias, $field, $sorted );
		$group_pathExpression = $this->createPathExpression( $group_alias, $group_field, $grouped );
		$group_order_pathExpression = $this->createPathExpression( $group_order_alias, $group_order_field, $grouped );

		if($sorted) {
	        $orderByItem = new OrderByItem($pathExpression);
	        $orderByItem->type = $direction;
	    }
        
        if($grouped) {
            $group_orderByItem = new OrderByItem($group_pathExpression);
            $group_orderByItem->type = $group_direction;

            $group_order_orderByItem = new OrderByItem($group_order_pathExpression);
            $group_order_orderByItem->type = $group_order_direction;
        }

        if ($AST->orderByClause) {
            $set = false;
            foreach ($AST->orderByClause->orderByItems as $key => $item) {
                if ($item->expression instanceof PathExpression) {
                    if ($sorted && $item->expression->identificationVariable === $alias && $item->expression->field === $field) {
                        $item->type = $direction;
                        $set = true;
                    }
                    if ($grouped && ($item->expression->identificationVariable === $group_alias && $item->expression->field === $group_field)) {
                        unset($AST->orderByClause->orderByItems[$key]);
                    }
                }
            }
            if ($sorted && !$set) {
                array_unshift($AST->orderByClause->orderByItems, $orderByItem);
            }
            if ($grouped) {
                array_unshift($AST->orderByClause->orderByItems, $group_orderByItem);
                array_unshift($AST->orderByClause->orderByItems, $group_order_orderByItem);
            }
        } else {
            $clauses = array($orderByItem);
            if($grouped) {
                array_unshift($clauses, $group_orderByItem);
                array_unshift($clauses, $group_order_orderByItem);
            }
            $AST->orderByClause = new OrderByClause($clauses);
        }
    }
}
