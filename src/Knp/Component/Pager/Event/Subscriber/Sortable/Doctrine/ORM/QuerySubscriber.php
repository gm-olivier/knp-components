<?php

namespace Knp\Component\Pager\Event\Subscriber\Sortable\Doctrine\ORM;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Knp\Component\Pager\Event\ItemsEvent;
use Knp\Component\Pager\Event\Subscriber\Sortable\Doctrine\ORM\Query\OrderByWalker;
use Knp\Component\Pager\Event\Subscriber\Paginate\Doctrine\ORM\Query\Helper as QueryHelper;
use Doctrine\ORM\Query;

class QuerySubscriber implements EventSubscriberInterface
{
    public function items(ItemsEvent $event)
    {
        if ($event->target instanceof Query) {
            $parts = null;
            $group_parts = null;
            if (isset($_GET[$event->options['sortFieldParameterName']])) {
                $dir = isset($_GET[$event->options['sortDirectionParameterName']]) && strtolower($_GET[$event->options['sortDirectionParameterName']]) === 'asc' ? 'asc' : 'desc';
                $parts = explode('.', $_GET[$event->options['sortFieldParameterName']]);

                if (isset($event->options['sortFieldWhitelist'])) {
                    if (!in_array($_GET[$event->options['sortFieldParameterName']], $event->options['sortFieldWhitelist'])) {
                        throw new \UnexpectedValueException("Cannot sort by: [{$_GET[$event->options['sortFieldParameterName']]}] this field is not in whitelist");
                    }
                }
            }
            if (isset($_GET[$event->options['groupFieldParameterName']])) {
                $group_dir = isset($_GET[$event->options['groupDirectionParameterName']]) && strtolower($_GET[$event->options['groupDirectionParameterName']]) === 'asc' ? 'asc' : 'desc';
                $group_parts = explode('.', $_GET[$event->options['groupFieldParameterName']]);

                if (isset($event->options['sortFieldWhitelist'])) {
                    if (!in_array($_GET[$event->options['groupFieldParameterName']], $event->options['sortFieldWhitelist'])) {
                        throw new \UnexpectedValueException("Cannot group by: [{$_GET[$event->options['groupFieldParameterName']]}] this field is not in whitelist");
                    }
                }
            }

            if($parts !== null) {
                $event->target
                    ->setHint(OrderByWalker::HINT_PAGINATOR_SORT_DIRECTION, $dir)
                    ->setHint(OrderByWalker::HINT_PAGINATOR_SORT_FIELD, end($parts))
                ;
                if (2 <= count($parts)) {
                    $event->target->setHint(OrderByWalker::HINT_PAGINATOR_SORT_ALIAS, reset($parts));
                }
            }
            if($group_parts !== null) {
                $event->target
                    ->setHint(OrderByWalker::HINT_PAGINATOR_GROUP_SORT_DIRECTION, $group_dir)
                    ->setHint(OrderByWalker::HINT_PAGINATOR_GROUP_SORT_FIELD, end($group_parts))
                ;
                if (2 <= count($group_parts)) {
                    $event->target->setHint(OrderByWalker::HINT_PAGINATOR_GROUP_SORT_ALIAS, reset($group_parts));
                }
            }
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
