<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\Repository\ORM\Tests\Functional;

use DateTime;
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
use Xabbuh\XApi\DataFixtures\ActivityFixtures;
use Xabbuh\XApi\DataFixtures\DefinitionFixtures;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\DataFixtures\VerbFixtures;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Model\LanguageMap;
use Xabbuh\XApi\Model\Statement as StatementModel;
use Xabbuh\XApi\Model\StatementReference;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Model\Verb;
use XApi\Repository\Doctrine\Mapping\Statement;
use XApi\Repository\Doctrine\Mapping\StatementObject as MappedStatementObject;
use XApi\Repository\Doctrine\Mapping\Verb as MappedVerb;
use XApi\Repository\Doctrine\Repository\ActivityRepository as DoctrineActivityRepository;
use XApi\Repository\Doctrine\Repository\StatementRepository as DoctrineStatementRepository;
use XApi\Repository\Doctrine\Repository\VerbRepository as DoctrineVerbRepository;
use XApi\Repository\Doctrine\Tests\Functional\StatementRepositoryTestCase;
use XApi\Repository\ORM\DoctrineQueryHelper;
use XApi\Repository\ORM\StatementObjectRepository as OrmStatementObjectRepository;
use XApi\Repository\ORM\VerbRepository as OrmVerbRepository;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatementRepositoryTest extends StatementRepositoryTestCase
{
    public function testStatementListsExcludeVoidedStatements(): void
    {
        $statement = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345678');
        $voidingStatement = StatementFixtures::getVoidingStatement(
            '12345678-1234-5678-8234-567812345679',
            $statement->getId()->getValue()
        );
        $referencingStatement = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345680')
            ->withObject(new StatementReference($statement->getId()));
        $repository = new DoctrineStatementRepository($this->repository);

        $repository->storeStatement($statement);
        $repository->storeStatement($voidingStatement);
        $repository->storeStatement($referencingStatement);

        $statements = $repository->findStatementsBy(
            new StatementsFilter()
                ->byActivity(ActivityFixtures::getTypicalActivity())
                ->ascending()
                ->limit(10)
        );
        $statementIds = array_map(
            static fn(StatementModel $statement): string => $statement->getId()->getValue(),
            $statements
        );

        self::assertNotContains($statement->getId()->getValue(), $statementIds);
        self::assertContains($voidingStatement->getId()->getValue(), $statementIds);
        self::assertContains($referencingStatement->getId()->getValue(), $statementIds);
    }

    public function testSinceFiltersByStoredTimeExclusively(): void
    {
        $atBoundary = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345678')
            ->withCreated(new DateTime('2024-01-01T12:00:00+00:00'));
        $afterBoundary = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345679')
            ->withCreated(new DateTime('2023-01-01T12:00:00+00:00'));

        $mappedAtBoundary = Statement::fromModel($atBoundary);
        $mappedAtBoundary->stored = new DateTime('2024-01-01T12:00:00+00:00');
        $mappedAfterBoundary = Statement::fromModel($afterBoundary);
        $mappedAfterBoundary->stored = new DateTime('2024-01-01T12:00:01+00:00');
        $mappedAfterBoundary->actor = $mappedAtBoundary->actor;
        $mappedAfterBoundary->verb = $mappedAtBoundary->verb;
        $mappedAfterBoundary->object = $mappedAtBoundary->object;

        $this->objectManager->persist($mappedAtBoundary);
        $this->objectManager->persist($mappedAfterBoundary);
        $this->objectManager->flush();

        $repository = new DoctrineStatementRepository($this->repository);
        $result = $repository->findStatementsBy(
            new StatementsFilter()
                ->since(new DateTime('2024-01-01T12:00:00+00:00'))
                ->ascending()
                ->limit(10)
        );

        self::assertCount(1, $result);
        self::assertSame($afterBoundary->getId()->getValue(), $result[0]->getId()->getValue());
    }

    public function testStatementRefFiltersApplyTimeAndLimitToReferencingStatements(): void
    {
        $activity = ActivityFixtures::getTypicalActivity();
        $target = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345678')
            ->withObject($activity);
        $middle = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345679')
            ->withObject(new StatementReference($target->getId()));
        $outer = StatementFixtures::getMinimalStatement('12345678-1234-5678-8234-567812345680')
            ->withObject(new StatementReference($middle->getId()));

        $mappedTarget = Statement::fromModel($target);
        $mappedTarget->stored = new DateTime('2024-01-01T00:00:00+00:00');
        $mappedMiddle = Statement::fromModel($middle);
        $mappedMiddle->stored = new DateTime('2024-01-02T00:00:00+00:00');
        $mappedOuter = Statement::fromModel($outer);
        $mappedOuter->stored = new DateTime('2024-01-03T00:00:00+00:00');

        foreach ([$mappedMiddle, $mappedOuter] as $mappedStatement) {
            $mappedStatement->actor = $mappedTarget->actor;
            $mappedStatement->verb = $mappedTarget->verb;
        }

        $this->objectManager->persist($mappedTarget);
        $this->objectManager->persist($mappedMiddle);
        $this->objectManager->persist($mappedOuter);
        $this->objectManager->flush();

        $repository = new DoctrineStatementRepository($this->repository);
        $firstPage = $repository->findStatementsBy(
            new StatementsFilter()
                ->byActivity($activity)
                ->since(new DateTime('2024-01-01T12:00:00+00:00'))
                ->ascending()
                ->limit(1)
        );
        $laterPage = $repository->findStatementsBy(
            new StatementsFilter()
                ->byActivity($activity)
                ->since(new DateTime('2024-01-02T12:00:00+00:00'))
                ->ascending()
                ->limit(10)
        );

        self::assertCount(1, $firstPage);
        self::assertSame($middle->getId()->getValue(), $firstPage[0]->getId()->getValue());
        self::assertCount(1, $laterPage);
        self::assertSame($outer->getId()->getValue(), $laterPage[0]->getId()->getValue());
    }

    public function testVerbRepositoryReturnsCanonicalDisplay(): void
    {
        $verb = VerbFixtures::getTypicalVerb();
        $mappedVerb = MappedVerb::fromModel($verb);
        $this->objectManager->persist($mappedVerb);
        $this->objectManager->flush();

        $ormRepository = new OrmVerbRepository(
            $this->objectManager,
            $this->objectManager->getClassMetadata(MappedVerb::class)
        );
        $repository = new DoctrineVerbRepository($ormRepository);
        $foundVerb = $repository->findVerbById($verb->getId());

        self::assertSame($verb->getId()->getValue(), $foundVerb->getId()->getValue());
        self::assertTrue($verb->getDisplay()->equals($foundVerb->getDisplay()));
    }

    public function testActivityRepositorySelectsTheFirstStoredDefinitionAsCanonical(): void
    {
        $activityId = IRI::fromString('https://example.com/activity');
        $firstActivity = new Activity($activityId, DefinitionFixtures::getNameDefinition());
        $laterActivity = new Activity($activityId, DefinitionFixtures::getDescriptionDefinition());
        $this->objectManager->persist(MappedStatementObject::fromModel($firstActivity));
        $this->objectManager->persist(MappedStatementObject::fromModel($laterActivity));
        $this->objectManager->flush();

        $mappedRepository = new OrmStatementObjectRepository(
            $this->objectManager,
            $this->objectManager->getClassMetadata(MappedStatementObject::class)
        );
        $repository = new DoctrineActivityRepository($mappedRepository);
        $canonicalActivity = $repository->findActivityById($activityId);

        self::assertSame('test', $canonicalActivity->getDefinition()->getName()['en-US']);
        self::assertNull($canonicalActivity->getDefinition()->getDescription());
    }

    public function testStoreMergesNewVerbDisplayLanguagesWithoutReplacingCanonicalValues(): void
    {
        $verb = VerbFixtures::getTypicalVerb();
        $mappedVerb = MappedVerb::fromModel($verb);
        $this->objectManager->persist($mappedVerb);
        $this->objectManager->flush();

        $incomingVerb = new Verb($verb->getId(), LanguageMap::create(['fr' => 'terminé', 'en-US' => 'fini']));
        $foundVerb = DoctrineQueryHelper::findVerb(
            $this->objectManager->createQueryBuilder(),
            MappedVerb::fromModel($incomingVerb)
        );

        self::assertSame(['en-US' => 'test', 'fr' => 'terminé'], $foundVerb->display);
    }

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

    protected function getStatementClassName(): string
    {
        return Statement::class;
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
