<?php

declare(strict_types=1);

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
use XApi\Repository\Doctrine\Mapping\Profile;
use XApi\Repository\Doctrine\Tests\Unit\Repository\Mapping\ProfileRepositoryTestCase;
use XApi\Repository\ORM\ProfileRepository;

#[AllowMockObjectsWithoutExpectations]
final class ProfileRepositoryTest extends ProfileRepositoryTestCase
{
    protected function getObjectManagerClass(): string { return EntityManager::class; }

    protected function getUnitOfWorkClass(): string { return UnitOfWork::class; }

    protected function getClassMetadataClass(): string { return ClassMetadata::class; }

    protected function createMappedProfileRepository(object $objectManager, object $unitOfWork, object $classMetadata): ProfileRepository
    {
        return new ProfileRepository($objectManager, new ClassMetadata(Profile::class, new DefaultNamingStrategy()));
    }
}
