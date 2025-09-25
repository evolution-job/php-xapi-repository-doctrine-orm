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
            ->leftJoin('s.object', 'o')
            ->leftJoin('s.verb', 'v')
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
                ->andWhere($queryBuilder->expr()->eq('s.registration', ':registration'))
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

    private function resolveActivityFilter(QueryBuilder $queryBuilder, array $criteria): void
    {
        if (!isset($criteria['activity'])) {
            return;
        }

        $orX = $queryBuilder->expr()->orX(
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq('o.activityId', ':activityId'),
                $queryBuilder->expr()->eq('o.type', ':objectTypeActivity')
            )
        );

        $queryBuilder
            ->setParameter('activityId', $criteria['activity'])
            ->setParameter('objectTypeActivity', 'activity');

        if (isset($criteria['related_activities'])) {

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
