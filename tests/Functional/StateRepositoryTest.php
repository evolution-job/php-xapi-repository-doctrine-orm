<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\Repository\ORM\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use Doctrine\Persistence\ObjectManager;
use Override;
use XApi\Repository\Doctrine\Mapping\State;
use XApi\Repository\Doctrine\Tests\Functional\StateRepositoryTestCase;

class StateRepositoryTest extends StateRepositoryTestCase
{
    /**
     * @throws MissingMappingDriverImplementation
     * @throws Exception
     * @throws ToolsException
     */
    protected function createObjectManager(): ObjectManager
    {
        $configuration = new Configuration();
        $configuration->setProxyDir(__DIR__ . '/../cache/proxies');
        $configuration->setProxyNamespace('Proxy');

        $symfonyFileLocator = new SymfonyFileLocator([__DIR__ . '/../../metadata' => 'XApi\Repository\Doctrine\Mapping'], '.orm.xml');
        $xmlDriver = new XmlDriver($symfonyFileLocator);
        $configuration->setMetadataDriverImpl($xmlDriver);

        $params = [
            'driver' => 'sqlite3',
            'memory' => true,
            'url'    => 'sqlite3:///:memory:',
        ];
        $connection = DriverManager::getConnection($params, $configuration);

        $entityManager = new EntityManager($connection, $configuration);

        // Create Schema
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    protected function getStateClassName(): string
    {
        return State::class;
    }

    #[Override]
    protected function cleanDatabase(): void
    {
        /** @var Connection $connection */
        $connection = $this->objectManager->getConnection();
        $databasePlatform = $connection->getDatabasePlatform();

        // Remove All
        $metadata = $this->objectManager->getMetadataFactory()->getAllMetadata();
        foreach ($metadata as $classMetadata) {
            $query = $databasePlatform->getTruncateTableSQL(
                $this->objectManager->getClassMetadata($classMetadata->getName())->getTableName()
            );

            $connection->executeStatement($query);
        }

        parent::cleanDatabase();
    }
}
