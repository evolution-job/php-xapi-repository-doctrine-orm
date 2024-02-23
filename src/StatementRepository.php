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
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use XApi\Repository\Doctrine\Mapping\Context;
use XApi\Repository\Doctrine\Mapping\Statement;
use XApi\Repository\Doctrine\Mapping\StatementObject;
use XApi\Repository\Doctrine\Mapping\Verb;
use XApi\Repository\Doctrine\Repository\Mapping\StatementRepository as BaseStatementRepository;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class StatementRepository extends parentAlias implements BaseStatementRepository
{
    /**
     * {@inheritdoc}
     */
    public function findStatement(array $criteria)
    {
        return $this->findOneBy($criteria);
    }

    /**
     * {@inheritdoc}
     */
    public function findStatements(array $criteria): array
    {
        return $this->findBy($criteria);
    }

    /**
     * {@inheritdoc}
     */
    public function storeStatement(Statement $statement, $flush = true): void
    {
        if ($this->_em->createQueryBuilder()) {
            $statement->actor = AvoidDuplicatesHelper::findActor($this->_em->createQueryBuilder(), $statement->actor);

            $statement->context = AvoidDuplicatesHelper::findContext($this->_em->createQueryBuilder(), $statement->context);

            $statement->object = AvoidDuplicatesHelper::findActivityStatementObject($this->_em->createQueryBuilder(), $statement->object);

            $statement->verb = AvoidDuplicatesHelper::findVerb($this->_em->createQueryBuilder(), $statement->verb);
        }

        try {
            $this->_em->persist($statement);

            if ($flush) {
                $this->_em->flush();
            }
        } catch (OptimisticLockException|ORMException $e) {
            //..
        }
    }
}
