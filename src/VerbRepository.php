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
use XApi\Repository\Doctrine\Mapping\Verb;
use XApi\Repository\Doctrine\Repository\Mapping\VerbRepository as BaseVerbRepository;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class VerbRepository extends parentAlias implements BaseVerbRepository
{
    public function findVerb(array $criteria): ?Verb
    {
        return $this->findOneBy($criteria);
    }
}
