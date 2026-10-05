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

use DateMalformedStringException;
use DateTime;
use Doctrine\ORM\EntityRepository as parentAlias;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\QueryBuilder;
use Xabbuh\XApi\Model\Agent;
use XApi\Repository\Doctrine\Mapping\Statement;
use XApi\Repository\Doctrine\Mapping\StatementObject as MappedStatementObject;
use XApi\Repository\Doctrine\Repository\Mapping\StatementRepository as BaseStatementRepository;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class StatementRepository extends parentAlias implements BaseStatementRepository
{
    private const int STATEMENT_ID_QUERY_CHUNK_SIZE = 500;

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
     * @throws DateMalformedStringException
     */
    public function findStatements(array $criteria): array
    {
        $statements = [];
        foreach ($this->createStatementsQueryBuilder($criteria, true)->getQuery()->getResult() as $statement) {
            $statements[$statement->id] = $statement;
        }

        $filterCriteria = $criteria;
        unset(
            $filterCriteria['attachments'],
            $filterCriteria['ascending'],
            $filterCriteria['limit'],
            $filterCriteria['since'],
            $filterCriteria['until'],
        );

        $hasNonTimeFilters = [] !== array_intersect(
            ['activity', 'agent', 'registration', 'verb'],
            array_keys($filterCriteria)
        );
        $targetIds = $hasNonTimeFilters ? $this->findMatchingStatementIds($filterCriteria) : [];
        $visitedIds = array_fill_keys($targetIds, true);
        $referencedIds = [];

        while ([] !== $targetIds) {
            $nextIds = $this->findReferencingStatementIds($targetIds);
            $targetIds = [];

            foreach ($nextIds as $statementId) {
                if (isset($visitedIds[$statementId])) {
                    continue;
                }

                $visitedIds[$statementId] = true;
                $referencedIds[$statementId] = true;
                $targetIds[] = $statementId;
            }
        }

        if ([] !== $referencedIds) {
            $referenceCriteria = $criteria;
            unset($referenceCriteria['limit']);

            foreach ($this->findStatementsByIds(array_keys($referencedIds), $referenceCriteria) as $statement) {
                $statements[$statement->id] = $statement;
            }
        }

        $statements = array_values($statements);
        $ascending = 'true' === ($criteria['ascending'] ?? 'false');
        usort($statements, static function (Statement $left, Statement $right) use ($ascending): int {
            $storedComparison = $left->stored <=> $right->stored;
            if (0 !== $storedComparison) {
                return $ascending ? $storedComparison : -$storedComparison;
            }

            return strcmp($left->id, $right->id);
        });

        if (isset($criteria['limit'])) {
            $statements = array_slice($statements, 0, $criteria['limit']);
        }

        return $statements;
    }

    private function createStatementsQueryBuilder(array $criteria, bool $selectEntities): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('s');

        $queryBuilder
            ->select($selectEntities ? 's, a, o, v' : 'DISTINCT s.id')
            ->leftJoin('s.actor', 'a')
            ->leftJoin('s.verb', 'v')
            ->leftJoin('s.object', 'o')
            ->leftJoin('s.context', 'c');

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

        $this->applyStoredTimeFilters($queryBuilder, $criteria);

        if ($selectEntities) {
            $queryBuilder->orderBy('s.stored', ($criteria['ascending'] ?? 'false') === 'true' ? 'ASC' : 'DESC');
        }

        if ($selectEntities && isset($criteria['limit'])) {
            $queryBuilder->setMaxResults($criteria['limit']);
        }

        if ($selectEntities && isset($criteria['attachments'])) {
            $queryBuilder
                ->addSelect('att')
                ->leftJoin('s.attachments', 'att');
        }

        return $queryBuilder;
    }

    private function applyStoredTimeFilters(QueryBuilder $queryBuilder, array $criteria): void
    {
        if (isset($criteria['since'])) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->gt('s.stored', ':since'))
                ->setParameter('since', new DateTime($criteria['since']));
        }

        if (isset($criteria['until'])) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->lte('s.stored', ':until'))
                ->setParameter('until', new DateTime($criteria['until']));
        }
    }

    /**
     * @return string[]
     */
    private function findMatchingStatementIds(array $criteria): array
    {
        $rows = $this->createStatementsQueryBuilder($criteria, false)->getQuery()->getScalarResult();

        return array_column($rows, 'id');
    }

    /**
     * @param string[] $targetIds
     * @return string[]
     */
    private function findReferencingStatementIds(array $targetIds): array
    {
        $referencingIds = [];

        foreach (array_chunk($targetIds, self::STATEMENT_ID_QUERY_CHUNK_SIZE) as $targetIdChunk) {
            $rows = $this->createQueryBuilder('s')
                ->select('DISTINCT s.id')
                ->leftJoin('s.object', 'o')
                ->where('o.type = :statementReference')
                ->andWhere('o.referencedStatementId IN (:targetIds)')
                ->setParameter('statementReference', MappedStatementObject::TYPE_STATEMENT_REFERENCE)
                ->setParameter('targetIds', $targetIdChunk)
                ->getQuery()
                ->getScalarResult();

            foreach (array_column($rows, 'id') as $statementId) {
                $referencingIds[$statementId] = $statementId;
            }
        }

        return array_values($referencingIds);
    }

    /**
     * @param string[] $statementIds
     * @return Statement[]
     */
    private function findStatementsByIds(array $statementIds, array $criteria): array
    {
        $statements = [];
        $timeCriteria = array_intersect_key($criteria, array_flip(['since', 'until', 'ascending', 'attachments']));

        foreach (array_chunk($statementIds, self::STATEMENT_ID_QUERY_CHUNK_SIZE) as $statementIdChunk) {
            $rows = $this->createStatementsQueryBuilder($timeCriteria, true)
                ->andWhere('s.id IN (:statementIds)')
                ->setParameter('statementIds', $statementIdChunk)
                ->getQuery()
                ->getResult();

            foreach ($rows as $statement) {
                $statements[$statement->id] = $statement;
            }
        }

        return array_values($statements);
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
