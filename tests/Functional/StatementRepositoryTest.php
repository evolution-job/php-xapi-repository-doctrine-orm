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
use XApi\Repository\Doctrine\Mapping\Statement;
use XApi\Repository\Doctrine\Tests\Functional\StatementRepositoryTest as BaseStatementRepositoryTest;
use XApi\Repository\ORM\QuoteStrategy;

class StatementRepositoryTest extends BaseStatementRepositoryTest
{
    /**
     * @throws MissingMappingDriverImplementation
     * @throws Exception
     * @throws ToolsException
     */
    protected function createObjectManager(): ObjectManager
    {
        $configuration = new Configuration();
        $configuration->setProxyDir(__DIR__ . '/../proxies');
        $configuration->setProxyNamespace('Proxy');
        $configuration->setQuoteStrategy(new QuoteStrategy());

        $symfonyFileLocator = new SymfonyFileLocator([__DIR__ . '/../../metadata' => 'XApi\Repository\Doctrine\Mapping'], '.orm.xml');

        $xmlDriver = new XmlDriver($symfonyFileLocator);
        $configuration->setMetadataDriverImpl($xmlDriver);

        $connection = DriverManager::getConnection(['url' => 'sqlite3:///:memory:'], $configuration);

        $entityManager = new EntityManager($connection, $configuration);

        // Create Schema
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    protected function getStatementClassName(): string
    {
        return Statement::class;
    }

    protected function cleanDatabase(): void
    {
        $connection = $this->objectManager->getConnection();
        $databasePlatform = $connection->getDatabasePlatform();

        // Remove All
        $metadata = $this->objectManager->getMetadataFactory()->getAllMetadata();
        foreach ($metadata as $classMetadata) {
            $query = $databasePlatform->getTruncateTableSQL(
                $this->objectManager->getClassMetadata($classMetadata->getName())->getTableName()
            );
            $connection->executeUpdate($query);
        }

        parent::cleanDatabase();
    }
}
