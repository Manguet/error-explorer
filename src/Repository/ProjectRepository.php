<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Trouve un projet par son token webhook
     */
    public function findByWebhookToken(string $token): ?Project
    {
        return $this->findOneBy([
            'webhookToken' => $token,
            'isActive' => true
        ]);
    }

    /**
     * Trouve un projet par son slug et propriétaire
     */
    public function findBySlugAndOwner(string $slug, User $owner): ?Project
    {
        return $this->findOneBy([
            'slug' => $slug,
            'owner' => $owner
        ]);
    }

    /**
     * Trouve tous les projets d'un utilisateur
     */
    public function findByOwner(User $owner, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les projets d'un utilisateur
     */
    public function countByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les projets actifs d'un utilisateur
     */
    public function countActiveByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.owner = :owner')
            ->andWhere('p.isActive = true')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si un nom de projet existe déjà pour un utilisateur
     */
    public function isNameExistsForUser(string $name, User $owner, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.name = :name')
            ->andWhere('p.owner = :owner')
            ->setParameter('name', $name)
            ->setParameter('owner', $owner);

        if ($excludeId) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Vérifie si un slug existe déjà pour un utilisateur
     */
    public function isSlugExistsForUser(string $slug, User $owner, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.slug = :slug')
            ->andWhere('p.owner = :owner')
            ->setParameter('slug', $slug)
            ->setParameter('owner', $owner);

        if ($excludeId) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Recherche de projets pour un utilisateur
     */
    public function searchForUser(User $owner, string $query, int $limit = 20): array
    {
        $searchTerm = '%' . strtolower($query) . '%';

        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.slug) LIKE :search OR LOWER(p.description) LIKE :search')
            ->setParameter('owner', $owner)
            ->setParameter('search', $searchTerm)
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Génère un slug unique pour un utilisateur
     */
    public function generateUniqueSlugForUser(string $baseName, User $owner): string
    {
        $baseSlug = strtolower($baseName);
        $baseSlug = preg_replace('/[^a-z0-9\-_]/', '-', $baseSlug);
        $baseSlug = preg_replace('/-+/', '-', $baseSlug);
        $baseSlug = trim($baseSlug, '-');
        $baseSlug = substr($baseSlug, 0, 100);

        $slug = $baseSlug;
        $counter = 1;

        while ($this->isSlugExistsForUser($slug, $owner)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Récupère les projets avec erreurs récentes pour un utilisateur
     */
    public function findWithRecentErrorsForUser(User $owner, int $days = 7): array
    {
        $since = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.isActive = true')
            ->andWhere('p.lastErrorAt >= :since')
            ->setParameter('owner', $owner)
            ->setParameter('since', $since)
            ->orderBy('p.lastErrorAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des projets pour un utilisateur
     */
    public function getStatsForUser(User $owner): array
    {
        $qb = $this->createQueryBuilder('p');

        $stats = $qb->select([
            'COUNT(p.id) as total_projects',
            'SUM(CASE WHEN p.isActive = true THEN 1 ELSE 0 END) as active_projects',
            'SUM(p.totalErrors) as total_errors',
            'SUM(p.totalOccurrences) as total_occurrences',
            'SUM(p.currentMonthErrors) as current_month_errors'
        ])
        ->where('p.owner = :owner')
        ->setParameter('owner', $owner)
        ->getQuery()
        ->getSingleResult();

        // Projets avec erreurs récentes (7 derniers jours)
        $recentThreshold = new \DateTime('-7 days');
        $recentStats = $this->createQueryBuilder('p')
            ->select('COUNT(p.id) as projects_with_recent_errors')
            ->where('p.owner = :owner')
            ->andWhere('p.lastErrorAt >= :recent')
            ->setParameter('owner', $owner)
            ->setParameter('recent', $recentThreshold)
            ->getQuery()
            ->getSingleResult();

        return array_merge($stats, $recentStats);
    }

    /**
     * Met à jour les statistiques d'un projet après une nouvelle erreur
     */
    public function updateProjectStats(Project $project, bool $isNewGroup = false): void
    {
        if ($isNewGroup) {
            $project->incrementTotalErrors();
        }
        $project->incrementTotalOccurrences();

        // Pas besoin de flush ici, sera fait par l'appelant
    }

    /**
     * Génère un slug unique basé sur un nom
     */
    public function generateUniqueSlug(string $baseName): string
    {
        $baseSlug = strtolower($baseName);
        $baseSlug = preg_replace('/[^a-z0-9\-_]/', '-', $baseSlug);
        $baseSlug = preg_replace('/-+/', '-', $baseSlug);
        $baseSlug = trim($baseSlug, '-');
        $baseSlug = substr($baseSlug, 0, 100); // Laisser de la place pour le suffixe

        $slug = $baseSlug;
        $counter = 1;

        while ($this->isSlugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Nettoie les projets inactifs très anciens
     */
    public function cleanupOldInactiveProjects(int $daysOld = 365): int
    {
        $threshold = new \DateTime("-{$daysOld} days");

        $qb = $this->createQueryBuilder('p');
        return $qb->update()
            ->set('p.isActive', 'false')
            ->where('p.isActive = true')
            ->andWhere('p.lastErrorAt < :threshold OR p.lastErrorAt IS NULL')
            ->andWhere('p.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    public function save(Project $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Project $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveProjects(?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->orderBy('p.updatedAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    public function countActiveProjects(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function isNameExists(string $name): bool
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function isSlugExists(string $slug): bool
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
