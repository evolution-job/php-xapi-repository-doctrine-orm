<?php

/*
 * This file is part of the xAPI package.
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
use XApi\Repository\Doctrine\Mapping\State;
use XApi\Repository\Doctrine\Tests\Unit\Repository\Mapping\StateRepositoryTestCase;
use XApi\Repository\ORM\StateRepository;

#[AllowMockObjectsWithoutExpectations]
class StateRepositoryTest extends StateRepositoryTestCase
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

    protected function createMappedStateRepository($objectManager, $unitOfWork, $classMetadata): StateRepository
    {
        $classMetadata = new ClassMetadata(State::class, new DefaultNamingStrategy());

        return new StateRepository($objectManager, $classMetadata);
    }
}
