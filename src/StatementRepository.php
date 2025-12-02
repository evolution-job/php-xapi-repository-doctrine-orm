<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\Repository\ORM;

use Doctrine\ORM\EntityRepository as parentAlias;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\QueryBuilder;
use Xabbuh\XApi\Model\Agent;
use XApi\Repository\Doctrine\Mapping\Statement;
use XApi\Repository\Doctrine\Repository\Mapping\StatementRepository as BaseStatementRepository;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class StatementRepository extends parentAlias implements BaseStatementRepository
{
    /**
     * {@inheritdoc}
     */
    public function findStatement(array $criteria): ?Statement
    {
        return $this->findOneBy($criteria);
    }

    /**
     * {@inheritdoc}
     * https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Communication.md#213-get-statements
     */
    public function findStatements(array $criteria): array
    {
        $queryBuilder = $this->createQueryBuilder('s');

        $queryBuilder
            ->select('s, a, o, v')
            ->leftJoin('s.actor', 'a')
            ->leftJoin('s.verb', 'v')
            ->leftJoin('s.object', 'o')
            ->leftJoin('s.context', 'c')
            ->setMaxResults($criteria['limit'])
            ->orderBy('s.created', $criteria['ascending'] === 'true' ? 'ASC' : 'DESC');

        $this->resolveActivityFilter($queryBuilder, $criteria);

        $this->resolveAgentFilter($queryBuilder, $criteria);

        if (isset($criteria['verb'])) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->eq('v.id', ':verb'))
                ->setParameter('verb', $criteria['verb']);
        }

        if (isset($criteria['registration'])) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->eq('c.registration', ':registration'))
                ->setParameter('registration', $criteria['registration']);
        }

        if (isset($criteria['since'])) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->gte('s.created', ':since'))
                ->setParameter('since', $criteria['since']);
        }

        if (isset($criteria['until'])) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->lte('s.created', ':until'))
                ->setParameter('until', $criteria['until']);
        }

        if (isset($criteria['attachments'])) {
            $queryBuilder
                ->addSelect('att')
                ->leftJoin('s.attachments', 'att');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function storeStatement(Statement $statement, $flush = true): void
    {
        if ($this->getEntityManager()->createQueryBuilder()) {

            if ($actor = DoctrineQueryHelper::findActor($this->getEntityManager()->createQueryBuilder(), $statement->actor)) {
                $statement->actor = $actor;
            }

            if ($context = DoctrineQueryHelper::findContext($this->getEntityManager()->createQueryBuilder(), $statement->context)) {
                $statement->context = $context;
            }

            if ($object = DoctrineQueryHelper::findActivityStatementObject($this->getEntityManager()->createQueryBuilder(), $statement->object)) {
                $statement->object = $object;
            }

            if ($verb = DoctrineQueryHelper::findVerb($this->getEntityManager()->createQueryBuilder(), $statement->verb)) {
                $statement->verb = $verb;
            }
        }

        $this->getEntityManager()->persist($statement);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    private function findStatementsRelatedActivities(QueryBuilder $queryBuilder, Expr\Orx $orX): void
    {
        /**
         * Apply the Activity filter broadly. Include Statements for which the Object, any
         * of the context Activities, or any of those properties in a contained SubStatement
         * match the Activity parameter, instead of that parameter's normal behavior.
         * Matching is defined in the same way it is for the "activity" parameter.
         *
         * To retrieve statement where the activity is in other locations of the statement.
         * Particularly important when we want to get all statements from a nested activity using one of
         * the ‘contextActivities’ slots.
         */
        $queryBuilder
            ->leftJoin('c.parentActivities', 'pAct')
            ->leftJoin('c.groupingActivities', 'gAct')
            ->leftJoin('c.categoryActivities', 'cAct')
            ->leftJoin('c.otherActivities', 'oAct');

        $orX
            ->add($queryBuilder->expr()->eq('pAct.activityId', ':activityId'))
            ->add($queryBuilder->expr()->eq('gAct.activityId', ':activityId'))
            ->add($queryBuilder->expr()->eq('cAct.activityId', ':activityId'))
            ->add($queryBuilder->expr()->eq('oAct.activityId', ':activityId'));
    }
    private function resolveActivityFilter(QueryBuilder $queryBuilder, array $criteria): void
    {
        if (!isset($criteria['activity'])) {
            return;
        }

        $orX = $queryBuilder->expr()->orX(
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq('o.activityId', ':activityId'),
                $queryBuilder->expr()->eq('o.type', ':typeActivity')
            )
        );

        $queryBuilder
            ->setParameter('activityId', $criteria['activity'])
            ->setParameter('typeActivity', 'activity');

        if (true === ($criteria['related_activities'] ?? false)) {

            $this->findStatementsRelatedActivities($queryBuilder, $orX);
        }

        $queryBuilder->andWhere($orX);
    }

    private function resolveAgentFilter(QueryBuilder $queryBuilder, array $criteria): void
    {
        if (!($criteria['agent'] ?? null) instanceof Agent) {

            return;
        }

        $orX = $queryBuilder->expr()->orX();

        // Actor
        $orX->add($this->resolveActorRequestConditions($queryBuilder, $criteria['agent'], 'a'));

        if (isset($criteria['related_agents'])) {

            // Authority
            $queryBuilder->leftJoin('s.authority', 'authority');
            $orX->add($this->resolveActorRequestConditions($queryBuilder, $criteria['agent'], 'authority'));

            // StatementObject TYPE_AGENT
            $andX = $this->resolveActorRequestConditions($queryBuilder, $criteria['agent'], 'o');
            $andX->add($queryBuilder->expr()->eq('o.type', ':agent'));
            $orX->add($andX);
            $queryBuilder->setParameter('agent', 'agent');

            // StatementObject TYPE_GROUP
            $andX = $this->resolveActorRequestConditions($queryBuilder, $criteria['agent'], 'o');
            $andX->add($queryBuilder->expr()->eq('o.type', ':group'));
            $orX->add($andX);
            $queryBuilder->setParameter('group', 'group');
        }

        $queryBuilder->andWhere($orX);
    }

    private function resolveActorRequestConditions(QueryBuilder $queryBuilder, Agent $agent, string $alias): Andx
    {
        $andX = $queryBuilder->expr()->andX();

        if (!$iri = $agent->getInverseFunctionalIdentifier()) {
            return $andX;
        }

        $key = random_int(0, 1000000000);

        if ($iri->getMbox()) {
            $andX->add($queryBuilder->expr()->eq($alias . '.mbox', ':mbox' . $key));
            $queryBuilder->setParameter('mbox' . $key, $iri->getMbox()->getValue());
        }

        if ($iri->getMboxSha1Sum()) {
            $andX->add($queryBuilder->expr()->eq($alias . '.mboxSha1Sum', ':mboxSha1Sum' . $key));
            $queryBuilder->setParameter('mboxSha1Sum' . $key, $iri->getMboxSha1Sum());
        }

        if ($iri->getOpenId()) {
            $andX->add($queryBuilder->expr()->eq($alias . '.openId', ':openId' . $key));
            $queryBuilder->setParameter('openId' . $key, $iri->getOpenId());
        }

        if ($iri->getAccount()?->getName()) {
            $andX->add($queryBuilder->expr()->eq($alias . '.accountName', ':accountName' . $key));
            $queryBuilder->setParameter('accountName' . $key, $iri->getAccount()?->getName());
        }

        if ($iri->getAccount()?->getHomePage()) {
            $andX->add($queryBuilder->expr()->eq($alias . '.accountHomePage', ':accountHomePage' . $key));
            $queryBuilder->setParameter('accountHomePage' . $key, $iri->getAccount()?->getHomePage()->getValue());
        }

        if ($agent->getName()) {
            $andX->add($queryBuilder->expr()->eq($alias . '.name', ':name' . $key));
            $queryBuilder->setParameter('name' . $key, $agent->getName());
        }

        return $andX;
    }
}
