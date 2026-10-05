<?php

declare(strict_types=1);

namespace XApi\Repository\ORM\Tests\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use Doctrine\Persistence\ObjectManager;
use XApi\Repository\Doctrine\Mapping\StatementObject;
use XApi\Repository\Doctrine\Tests\Functional\StatementObjectRepositoryTestCase;
use XApi\Repository\ORM\StatementObjectRepository;

final class StatementObjectRepositoryTest extends StatementObjectRepositoryTestCase
{
    protected function createObjectManager(): ObjectManager
    {
        $configuration = new Configuration();
        $configuration->setProxyDir(__DIR__.'/../cache/proxies');
        $configuration->setProxyNamespace('Proxy');
        $configuration->setMetadataDriverImpl(new XmlDriver(new SymfonyFileLocator([__DIR__.'/../../metadata' => 'XApi\\Repository\\Doctrine\\Mapping'], '.orm.xml')));
        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'sqlite3', 'memory' => true, 'url' => 'sqlite3:///:memory:'], $configuration), $configuration);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    protected function getStatementObjectClassName(): string { return StatementObject::class; }

    protected function createMappedStatementObjectRepository(): StatementObjectRepository
    {
        return new StatementObjectRepository($this->objectManager, $this->objectManager->getClassMetadata(StatementObject::class));
    }
}
