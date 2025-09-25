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
    public function findState(State $state): ?State
    {
        if ($agent = DoctrineQueryHelper::findActor($this->getEntityManager()->createQueryBuilder(), $state->agent)) {
            $state->agent = $agent;
        }

        $criteria = [
            'activityId' => $state->activityId,
            'agent'      => $state->agent,
            'stateId'    => $state->stateId,
        ];

        if ($state->registrationId) {
            $criteria['registrationId'] = $state->registrationId;
        }

        return $this->findOneBy($criteria);
    }

    /**
     * {@inheritdoc}
     */
    public function findStates(State $state): array
    {
        if ($agent = DoctrineQueryHelper::findActor($this->getEntityManager()->createQueryBuilder(), $state->agent)) {
            $state->agent = $agent;
        }

        $criteria = [
            'activityId' => $state->activityId,
            'agent'      => $state->agent
        ];

        if ($state->registrationId) {
            $criteria['registrationId'] = $state->registrationId;
        }

        return $this->findBy($criteria);
    }

    public function removeState(State $state, bool $flush = true): void
    {
        $states = $this->findStates($state);

        foreach ($states as $foundState) {
            $this->getEntityManager()->remove($foundState);
        }

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function storeState(State $state, $flush = true): void
    {
        if ($agent = DoctrineQueryHelper::findActor($this->getEntityManager()->createQueryBuilder(), $state->agent)) {
            $state->agent = $agent;
        }

        $foundState = $this->findState($state);

        if ($foundState instanceof State) { // Update
            $foundState->data = $state->data;
            $this->getEntityManager()->persist($foundState);
        } else {
            $this->getEntityManager()->persist($state);
        }

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}