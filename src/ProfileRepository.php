<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\Repository\ORM;

use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use XApi\Repository\Doctrine\Mapping\Profile;
use XApi\Repository\Doctrine\Repository\Mapping\ProfileRepository as BaseProfileRepository;

final class ProfileRepository extends EntityRepository implements BaseProfileRepository
{
    public function findProfile(string $resource, string $profileId): ?Profile
    {
        return $this->findOneBy(['resource' => $resource, 'profileId' => $profileId]);
    }

    public function findProfiles(string $resource, ?DateTimeImmutable $since = null): array
    {
        if (null === $since) {
            return $this->findBy(['resource' => $resource]);
        }

        return $this->createQueryBuilder('profile')
            ->andWhere('profile.resource = :resource')
            ->andWhere('profile.updated > :since')
            ->setParameter('resource', $resource)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    public function removeProfile(string $resource, ?string $profileId = null, bool $flush = true): void
    {
        $criteria = ['resource' => $resource];
        if (null !== $profileId) {
            $criteria['profileId'] = $profileId;
        }

        foreach ($this->findBy($criteria) as $profile) {
            $this->getEntityManager()->remove($profile);
        }

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function storeProfile(Profile $profile, bool $flush = true): void
    {
        $existingProfile = $this->findProfile($profile->resource, $profile->profileId);
        if ($existingProfile instanceof Profile) {
            $existingProfile->content = $profile->content;
            $existingProfile->contentType = $profile->contentType;
            $existingProfile->updated = $profile->updated;
            $this->getEntityManager()->persist($existingProfile);
        } else {
            $this->getEntityManager()->persist($profile);
        }

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
