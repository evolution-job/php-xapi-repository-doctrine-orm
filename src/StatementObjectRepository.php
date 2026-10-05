<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\Repository\ORM;

use Doctrine\ORM\EntityRepository as parentAlias;
use XApi\Repository\Doctrine\Mapping\StatementObject;
use XApi\Repository\Doctrine\Repository\Mapping\StatementObjectRepository as BaseStatementObjectRepository;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class StatementObjectRepository extends parentAlias implements BaseStatementObjectRepository
{
    /**
     * {@inheritdoc}
     */
    public function findObject(array $criteria): ?StatementObject
    {
        return $this->findOneBy($criteria, ['identifier' => 'ASC']);
    }
}
