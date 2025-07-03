<?php

namespace App\Repository;

use App\Entity\Tag;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Recherche des tags par nom avec autocomplete pour un utilisateur donné
     */
    public function findByNameForUser(string $query, User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :user')
            ->andWhere('t.name LIKE :query')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un tag par nom et utilisateur
     */
    public function findOneByNameAndUser(string $name, User $user): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.name = :name')
            ->andWhere('t.owner = :user')
            ->setParameter('name', $name)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un tag par slug et utilisateur
     */
    public function findOneBySlugAndUser(string $slug, User $user): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.slug = :slug')
            ->andWhere('t.owner = :user')
            ->setParameter('slug', $slug)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère tous les tags d'un utilisateur, triés par usage puis par nom
     */
    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les tags les plus utilisés pour un utilisateur
     */
    public function findMostUsedByUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :user')
            ->andWhere('t.usageCount > 0')
            ->setParameter('user', $user)
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les tags récemment créés pour un utilisateur
     */
    public function findRecentByUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de tags pour un utilisateur
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les tags non utilisés pour un utilisateur
     */
    public function findUnusedByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :user')
            ->andWhere('t.usageCount = 0')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des tags par couleur pour un utilisateur
     */
    public function findByColorAndUser(string $color, User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :user')
            ->andWhere('t.color = :color')
            ->setParameter('user', $user)
            ->setParameter('color', $color)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les statistiques des tags pour un utilisateur
     */
    public function getStatsForUser(User $user): array
    {
        $result = $this->createQueryBuilder('t')
            ->select([
                'COUNT(t.id) as total_tags',
                'SUM(t.usageCount) as total_usage',
                'AVG(t.usageCount) as avg_usage',
                'MAX(t.usageCount) as max_usage'
            ])
            ->andWhere('t.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return [
            'total_tags' => (int) ($result['total_tags'] ?? 0),
            'total_usage' => (int) ($result['total_usage'] ?? 0),
            'avg_usage' => round((float) ($result['avg_usage'] ?? 0), 2),
            'max_usage' => (int) ($result['max_usage'] ?? 0),
            'unused_tags' => $this->countUnusedByUser($user)
        ];
    }

    /**
     * Compte les tags non utilisés pour un utilisateur
     */
    public function countUnusedByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.owner = :user')
            ->andWhere('t.usageCount = 0')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve ou crée un tag par nom pour un utilisateur
     */
    public function findOrCreateByNameAndUser(string $name, User $user): Tag
    {
        $tag = $this->findOneByNameAndUser($name, $user);
        
        if (!$tag) {
            $tag = new Tag();
            $tag->setName($name)
                ->setOwner($user)
                ->initialize(); // Génère automatiquement la couleur et le slug
                
            $this->getEntityManager()->persist($tag);
        }
        
        return $tag;
    }

    /**
     * Recherche avancée avec filtres
     */
    public function findWithFilters(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.owner = :user')
            ->setParameter('user', $user);

        // Filtre par nom
        if (!empty($filters['name'])) {
            $qb->andWhere('t.name LIKE :name')
               ->setParameter('name', '%' . $filters['name'] . '%');
        }

        // Filtre par couleur
        if (!empty($filters['color'])) {
            $qb->andWhere('t.color = :color')
               ->setParameter('color', $filters['color']);
        }

        // Filtre par usage minimum
        if (isset($filters['min_usage'])) {
            $qb->andWhere('t.usageCount >= :min_usage')
               ->setParameter('min_usage', (int) $filters['min_usage']);
        }

        // Filtre par usage maximum
        if (isset($filters['max_usage'])) {
            $qb->andWhere('t.usageCount <= :max_usage')
               ->setParameter('max_usage', (int) $filters['max_usage']);
        }

        // Filtre par date de création
        if (!empty($filters['created_after'])) {
            $qb->andWhere('t.createdAt >= :created_after')
               ->setParameter('created_after', $filters['created_after']);
        }

        if (!empty($filters['created_before'])) {
            $qb->andWhere('t.createdAt <= :created_before')
               ->setParameter('created_before', $filters['created_before']);
        }

        // Tri
        $sortBy = $filters['sort_by'] ?? 'usage';
        $sortOrder = $filters['sort_order'] ?? 'DESC';

        switch ($sortBy) {
            case 'name':
                $qb->orderBy('t.name', $sortOrder);
                break;
            case 'created':
                $qb->orderBy('t.createdAt', $sortOrder);
                break;
            case 'updated':
                $qb->orderBy('t.updatedAt', $sortOrder);
                break;
            case 'usage':
            default:
                $qb->orderBy('t.usageCount', $sortOrder)
                   ->addOrderBy('t.name', 'ASC');
                break;
        }

        // Limite
        if (!empty($filters['limit'])) {
            $qb->setMaxResults((int) $filters['limit']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Nettoie les tags non utilisés d'un utilisateur
     */
    public function cleanupUnusedTags(User $user): int
    {
        $qb = $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.owner = :user')
            ->andWhere('t.usageCount = 0')
            ->andWhere('t.isSystem = false')
            ->setParameter('user', $user);

        return $qb->getQuery()->execute();
    }

    /**
     * Met à jour les compteurs d'usage de tous les tags d'un utilisateur
     */
    public function refreshUsageCounts(User $user): void
    {
        // Cette méthode recalcule les compteurs d'usage basés sur les relations réelles
        $tags = $this->findAllByUser($user);
        
        foreach ($tags as $tag) {
            $actualCount = $tag->getErrorGroups()->count();
            $tag->setUsageCount($actualCount);
        }
        
        $this->getEntityManager()->flush();
    }
}