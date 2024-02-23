<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\Repository\ORM;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use XApi\Repository\Doctrine\Mapping\Context;
use XApi\Repository\Doctrine\Mapping\StatementObject;
use XApi\Repository\Doctrine\Mapping\Verb;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
abstract class AvoidDuplicatesHelper
{
    public static function findActor(QueryBuilder $queryBuilder, ?StatementObject $actor): StatementObject
    {
        if (!$actor) {
            return $actor;
        }

        $queryBuilder
            ->select('o')
            ->from(StatementObject::class, 'o')
            ->andWhere($queryBuilder->expr()->like('o.type', ':type'))
            ->andWhere($queryBuilder->expr()->like('o.accountName', ':accountName'))
            ->andWhere($queryBuilder->expr()->like('o.accountHomePage', ':accountHomePage'))
            ->andWhere($queryBuilder->expr()->like('o.name', ':name'))
            ->setParameter('type', 'agent')
            ->setParameter('accountName', $actor->accountName)
            ->setParameter('accountHomePage', $actor->accountHomePage)
            ->setParameter('name', $actor->name);

        if ($actors = $queryBuilder->getQuery()->getResult()) {
            return $actors[0]; // Link with first found
        }

        return $actor;
    }

    public static function findActivityStatementObject(QueryBuilder $queryBuilder, ?StatementObject $activity): StatementObject
    {
        if (!$activity) {
            return $activity;
        }

        $queryBuilder
            ->select('o')
            ->from(StatementObject::class, 'o')
            ->andWhere($queryBuilder->expr()->eq('o.activityId', ':activityId'))
            ->setParameter('activityId', $activity->activityId);

        if ($activities = $queryBuilder->getQuery()->getResult()) {
            return $activities[0]; // Link with first found
        }

        return $activity;
    }

    public static function findContext(QueryBuilder $queryBuilder, ?Context $context)
    {
        if (!$context) {
            return $context;
        }

        $queryBuilder
            ->select('c')
            ->from(Context::class, 'c')
            ->where($queryBuilder->expr()->eq('c.registration', ':id'))
            ->setParameter('id', $context->registration);

        if ($contexts = $queryBuilder->getQuery()->getResult()) {
            return $contexts[0];
        }

        return $context;
    }

    public static function findVerb(QueryBuilder $queryBuilder, ?Verb $verb): Verb
    {
        if (!$verb) {
            return $verb;
        }

        $queryBuilder
            ->select('v')
            ->from(Verb::class, 'v')
            ->where($queryBuilder->expr()->eq('v.id', ':id'))
            ->setParameter('id', $verb->id);

        try {
            if ($foundVerb = $queryBuilder->getQuery()->getOneOrNullResult()) {

                foreach ($verb->display as $k => $v) {
                    if (!array_key_exists($k, $verb->display)) {
                        $foundVerb->display[$k] = $v; // Add new display
                    }
                }

                return $foundVerb;
            }
        } catch (NonUniqueResultException $e) { }

        return $verb;
    }
}