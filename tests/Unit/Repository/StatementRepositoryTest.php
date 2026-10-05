<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\Repository\ORM\Tests\Unit\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use XApi\Repository\Doctrine\Mapping\Statement;
use XApi\Repository\Doctrine\Tests\Unit\Repository\Mapping\StatementRepositoryTestCase;
use XApi\Repository\ORM\StatementRepository;

#[AllowMockObjectsWithoutExpectations]
class StatementRepositoryTest extends StatementRepositoryTestCase
{
    protected function getObjectManagerClass(): string
    {
        return EntityManager::class;
    }

    protected function getUnitOfWorkClass(): string
    {
        return UnitOfWork::class;
    }

    protected function getClassMetadataClass(): string
    {
        return ClassMetadata::class;
    }

    protected function createMappedStatementRepository($objectManager, $unitOfWork, $classMetadata): StatementRepository
    {
        $classMetadata = new ClassMetadata(Statement::class, new DefaultNamingStrategy());

        return new StatementRepository($objectManager, $classMetadata);
    }
}
