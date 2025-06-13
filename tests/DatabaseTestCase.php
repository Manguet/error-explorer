<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Classe de base simplifiée pour les tests avec base de données
 */
abstract class DatabaseTestCase extends WebTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();

        // Nettoyer la base avant chaque test
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    /**
     * Nettoie la base de données
     */
    protected function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();

        // Utiliser TRUNCATE pour MySQL ou DELETE pour SQLite
        $platform = $connection->getDatabasePlatform();

        if ($platform->getName() === 'mysql') {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            $tables = $connection->fetchFirstColumn("SHOW TABLES");
            foreach ($tables as $table) {
                $connection->executeStatement("TRUNCATE TABLE `{$table}`");
            }
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        } else {
            // Pour SQLite ou autres
            $schemaManager = $connection->createSchemaManager();
            $tables = $schemaManager->listTableNames();

            $connection->executeStatement('PRAGMA foreign_keys = OFF');
            foreach ($tables as $table) {
                $connection->executeStatement("DELETE FROM {$table}");
            }
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * Helper pour créer des entités de test
     */
    protected function persistEntity($entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}

// Trait pour les fixtures - Version simplifiée
trait TestFixtures
{
    protected function createTestUser(array $data = []): \App\Entity\User
    {
        $user = new \App\Entity\User();
        $user->setEmail($data['email'] ?? 'test@example.com');
        $user->setFirstName($data['firstName'] ?? 'Test');
        $user->setLastName($data['lastName'] ?? 'User');
        $user->setPassword($data['password'] ?? '$2y$10$dummy.hash.for.test');
        $user->setIsActive($data['isActive'] ?? true);
        $user->setIsVerified($data['isVerified'] ?? true);
        $user->setCreatedAt($data['createdAt'] ?? new \DateTimeImmutable());
        $user->setUpdatedAt($data['updatedAt'] ?? new \DateTimeImmutable());

        return $user;
    }

    protected function createTestPlan(array $data = []): \App\Entity\Plan
    {
        $plan = new \App\Entity\Plan();
        $plan->setName($data['name'] ?? 'Test Plan');
        $plan->setSlug($data['slug'] ?? 'test-plan');
        $plan->setPriceMonthly($data['price'] ?? 0.0);
        $plan->setIsActive($data['isActive'] ?? true);
        $plan->setIsBuyable($data['isBuyable'] ?? true);
        $plan->setMaxProjects($data['maxProjects'] ?? 5);
        $plan->setMaxMonthlyErrors($data['maxMonthlyErrors'] ?? 1000);

        return $plan;
    }
}
