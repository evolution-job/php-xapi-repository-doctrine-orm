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

use Doctrine\ORM\EntityRepository;
use XApi\Repository\Doctrine\Mapping\State;
use XApi\Repository\Doctrine\Repository\Mapping\StateRepository as BaseStateRepository;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class StateRepository extends EntityRepository implements BaseStateRepository
{
    /**
     * {@inheritdoc}
     */
    public function findState(array $criteria)
    {
        return $this->findOneBy($criteria);
    }

    /**
     * {@inheritdoc}
     */
    public function storeState(State $state, $flush = true): void
    {
        // Store or Update?
        $mappedState = $this->findState([
            "stateId" => $state->stateId,
            "activityId" => $state->activityId,
            "registrationId" => $state->registrationId
        ]);

        if ($mappedState instanceof State) { // Update
            $mappedState->data = $state->data;
            $state = $mappedState;
        } else {
            $state->actor = AvoidDuplicatesHelper::findActor($this->getEntityManager()->createQueryBuilder(), $state->actor);
        }

        $this->getEntityManager()->persist($state);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}