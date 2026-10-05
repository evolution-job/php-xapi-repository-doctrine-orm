<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\Repository\ORM\Tests\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use Doctrine\Persistence\ObjectManager;
use XApi\Repository\Doctrine\Mapping\Profile;
use XApi\Repository\Doctrine\Tests\Functional\ProfileRepositoryTestCase;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final class ProfileRepositoryTest extends ProfileRepositoryTestCase
{
    protected function createObjectManager(): ObjectManager
    {
        $configuration = new Configuration();
        $configuration->setProxyDir(__DIR__.'/../cache/proxies');
        $configuration->setProxyNamespace('Proxy');
        $configuration->setMetadataDriverImpl(new XmlDriver(new SymfonyFileLocator([
            __DIR__.'/../../metadata' => 'XApi\\Repository\\Doctrine\\Mapping',
        ], '.orm.xml')));

        $entityManager = new EntityManager(DriverManager::getConnection([
            'driver' => 'sqlite3',
            'memory' => true,
            'url' => 'sqlite3:///:memory:',
        ], $configuration), $configuration);

        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    protected function getProfileClassName(): string
    {
        return Profile::class;
    }
}
