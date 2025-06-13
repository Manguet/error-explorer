<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Classe de base pour les tests nécessitant une base de données
 */
abstract class DatabaseTestCase extends WebTestCase
{
    protected ?EntityManagerInterface $entityManager;
    protected static ?KernelInterface $kernel = null;

    protected function setUp(): void
    {
        if (null === self::$kernel) {
            self::$kernel = self::bootKernel();
        }

        $this->entityManager = self::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->initializeDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();

        $this->entityManager->close();
        $this->entityManager = null;

        parent::tearDown();
    }

    /**
     * Initialise une base de données propre pour chaque test
     */
    private function initializeDatabase(): void
    {
        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        // Supprimer et recréer le schéma
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadatas);
    }

    /**
     * Nettoie la base de données
     */
    private function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        // Désactiver les contraintes selon le type de base de données
        if ($platform->getName() === 'sqlite') {
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
        } elseif ($platform->getName() === 'mysql') {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        }

        // Récupérer toutes les tables
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        // Supprimer le contenu de toutes les tables
        foreach ($tables as $tableName) {
            if ($platform->getName() === 'mysql') {
                $connection->executeStatement("TRUNCATE TABLE `{$tableName}`");
            } else {
                $connection->executeStatement("DELETE FROM {$tableName}");
            }
        }

        // Réactiver les contraintes
        if ($platform->getName() === 'sqlite') {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        } elseif ($platform->getName() === 'mysql') {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Helper pour créer des entités de test
     */
    protected function persistAndFlush($entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
