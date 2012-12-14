<?php

namespace Knp\Component\Pager\Event\Subscriber\Sortable\Doctrine\ORM;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Knp\Component\Pager\Event\ItemsEvent;
use Knp\Component\Pager\Event\Subscriber\Sortable\Doctrine\ORM\Query\OrderByWalker;
use Knp\Component\Pager\Event\Subscriber\Paginate\Doctrine\ORM\Query\Helper as QueryHelper;
use Doctrine\ORM\Query;

class QuerySubscriber implements EventSubscriberInterface
{
	protected function getParts( ItemsEvent $event, $fieldParam, $directionParam )
	{
		if (isset($_GET[$event->options[$fieldParam]])) {
			$dir = isset($_GET[$event->options[$directionParam]]) && strtolower($_GET[$event->options[$directionParam]]) === 'asc' ? 'asc' : 'desc';
			$parts = explode('.', $_GET[$event->options[$fieldParam]]);

			if (isset($event->options['sortFieldWhitelist'])) {
				if (!in_array($_GET[$event->options[$fieldParam]], $event->options['sortFieldWhitelist'])) {
					throw new \UnexpectedValueException("Cannot sort by: [{$_GET[$event->options[$fieldParam]]}] this field is not in whitelist");
                }
            }
        }
        else
        {
        	$parts = null;
        	$dir = null;
        }
        
        return array( $parts, $dir );
	}

	protected function setHints( ItemsEvent $event, $parts, $dir, $field_hint, $direction_hint, $alias_hint )
	{
		if($parts !== null) {
			$event->target
				->setHint($direction_hint, $dir)
				->setHint($field_hint, end($parts))
			;
			if (2 <= count($parts)) {
				$event->target->setHint($alias_hint, reset($parts));
			}
		}
	}

    public function items(ItemsEvent $event)
    {
        if ($event->target instanceof Query) {
            list( $parts, $dir ) = $this->getParts( $event, 'sortFieldParameterName', 'sortDirectionParameterName' );
            list( $group_parts, $group_dir ) = $this->getParts( $event, 'groupFieldParameterName', 'groupDirectionParameterName' );
            
            $this->setHints( $event, $parts, $dir,
            	OrderByWalker::HINT_PAGINATOR_SORT_FIELD,
            	OrderByWalker::HINT_PAGINATOR_SORT_DIRECTION,
            	OrderByWalker::HINT_PAGINATOR_SORT_ALIAS
            );
            $this->setHints( $event, $group_parts, $group_dir,
            	OrderByWalker::HINT_PAGINATOR_GROUP_SORT_FIELD,
            	OrderByWalker::HINT_PAGINATOR_GROUP_SORT_DIRECTION,
            	OrderByWalker::HINT_PAGINATOR_GROUP_SORT_ALIAS
            );

            if($parts !== null || $group_parts !== null) {
                QueryHelper::addCustomTreeWalker($event->target, 'Knp\Component\Pager\Event\Subscriber\Sortable\Doctrine\ORM\Query\OrderByWalker');
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            'knp_pager.items' => array('items', 1)
        );
    }
}
