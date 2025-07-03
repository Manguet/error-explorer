<?php

namespace App\Repository;

use App\Entity\ErrorGroup;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ErrorGroup>
 */
class ErrorGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ErrorGroup::class);
    }

    /**
     * Recherche avec filtres avancés et multi-tenancy
     */
    public function findWithFilters(
        array $filters = [],
        string $sortBy = 'last_seen',
        string $sortDirection = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $qb = $this->createFilteredQueryBuilder($filters);

        // Tri
        $validSortFields = ['lastSeen', 'firstSeen', 'occurrenceCount', 'message', 'project', 'httpStatusCode'];
        if (in_array($sortBy, $validSortFields)) {
            $qb->orderBy('eg.' . $sortBy, strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC');
        }

        // Pagination
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les résultats avec filtres et multi-tenancy
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->select('COUNT(eg.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les projets distincts pour un utilisateur
     */
    public function getDistinctProjectsForUser(User $user): array
    {
        $qb = $this->createQueryBuilder('eg');
        $qb->select('DISTINCT eg.project')
            ->join('eg.projectEntity', 'p')
            ->where('p.owner = :user')
            ->andWhere('eg.project IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('eg.project', 'ASC');

        $results = $qb->getQuery()->getResult();

        return array_column($results, 'project');
    }

    /**
     * Récupère les environnements distincts pour un utilisateur
     */
    public function getDistinctEnvironmentsForUser(User $user): array
    {
        $qb = $this->createQueryBuilder('eg');
        $qb->select('DISTINCT eg.environment')
            ->join('eg.projectEntity', 'p')
            ->where('p.owner = :user')
            ->andWhere('eg.environment IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('eg.environment', 'ASC');

        $results = $qb->getQuery()->getResult();

        return array_column($results, 'environment');
    }

    /**
     * Top des projets par nombre d'occurrences pour un utilisateur
     */
    public function getTopProjectsByOccurrences(int $limit = 5, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('eg');
        $qb->select('eg.project, COUNT(eg.id) as error_count, SUM(eg.occurrenceCount) as total_occurrences')
            ->where('eg.project IS NOT NULL');

        // Appliquer le filtre utilisateur
        if (isset($filters['user'])) {
            $qb->join('eg.projectEntity', 'p')
                ->andWhere('p.owner = :user')
                ->setParameter('user', $filters['user']);
        }

        // Appliquer manuellement certains filtres pertinents
        if (isset($filters['status'])) {
            $qb->andWhere('eg.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['since']) && $filters['since'] instanceof \DateTimeInterface) {
            $qb->andWhere('eg.lastSeen >= :since')
                ->setParameter('since', $filters['since']);
        }

        $qb->groupBy('eg.project')
            ->orderBy('total_occurrences', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si une erreur appartient à un utilisateur
     */
    public function belongsToUser(ErrorGroup $errorGroup, User $user): bool
    {
        if ($errorGroup->getProjectEntity()) {
            return $errorGroup->getProjectEntity()->getOwner()->getId() === $user->getId();
        }

        // Fallback: vérifier par nom de projet
        $qb = $this->createQueryBuilder('eg');
        $count = $qb->select('COUNT(eg.id)')
            ->join('eg.projectEntity', 'p')
            ->where('eg = :errorGroup')
            ->andWhere('p.owner = :user')
            ->setParameter('errorGroup', $errorGroup)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Statistiques par statut pour un utilisateur
     */
    public function getStatsByStatusForUser(User $user, array $filters = []): array
    {
        $filters['user'] = $user;
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->select('eg.status, COUNT(eg.id) as count, SUM(eg.occurrenceCount) as total_occurrences')
            ->groupBy('eg.status')
            ->orderBy('eg.status', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Erreurs les plus fréquentes pour un utilisateur
     */
    public function getMostFrequentForUser(User $user, int $limit = 10, array $filters = []): array
    {
        $filters['user'] = $user;
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->orderBy('eg.occurrenceCount', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche textuelle dans les messages d'erreur pour un utilisateur
     */
    public function searchByMessageForUser(User $user, string $searchTerm, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('eg');
        $qb->join('eg.projectEntity', 'p')
            ->where('p.owner = :user')
            ->andWhere('eg.message LIKE :search OR eg.exceptionClass LIKE :search OR eg.file LIKE :search')
            ->setParameter('user', $user)
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('eg.lastSeen', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les tags populaires pour un utilisateur
     */
    public function getPopularTagsForUser(User $user, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('eg');
        $qb->select('t.id, t.name, t.color, COUNT(eg.id) as error_count')
            ->join('eg.projectEntity', 'p')
            ->join('eg.tags', 't')
            ->where('p.owner = :user')
            ->setParameter('user', $user)
            ->groupBy('t.id, t.name, t.color')
            ->orderBy('error_count', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les erreurs par tag pour un utilisateur
     */
    public function findByTagForUser(string $tagName, User $user, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('eg');
        $qb->join('eg.projectEntity', 'p')
            ->join('eg.tags', 't')
            ->where('p.owner = :user')
            ->andWhere('t.name = :tagName')
            ->setParameter('user', $user)
            ->setParameter('tagName', $tagName)
            ->orderBy('eg.lastSeen', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Crée un QueryBuilder avec les filtres appliqués (incluant multi-tenancy)
     */
    private function createFilteredQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('eg');

        // IMPORTANT: Filtre par utilisateur (multi-tenancy)
        if (isset($filters['user'])) {
            $qb->join('eg.projectEntity', 'p')
                ->andWhere('p.owner = :user')
                ->setParameter('user', $filters['user']);
        }

        // Filtre par projet
        if (isset($filters['project'])) {
            $qb->andWhere('eg.project = :project')
                ->setParameter('project', $filters['project']);
        }

        // Filtre par statut
        if (isset($filters['status'])) {
            $qb->andWhere('eg.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // Filtre par code HTTP
        if (isset($filters['http_status'])) {
            $qb->andWhere('eg.httpStatusCode = :httpStatus')
                ->setParameter('httpStatus', $filters['http_status']);
        }

        // Filtre par type d'erreur
        if (isset($filters['error_type'])) {
            $qb->andWhere('eg.errorType = :errorType')
                ->setParameter('errorType', $filters['error_type']);
        }

        // Filtre par environnement
        if (isset($filters['environment'])) {
            $qb->andWhere('eg.environment = :environment')
                ->setParameter('environment', $filters['environment']);
        }

        // Filtre par date (depuis)
        if (isset($filters['since']) && $filters['since'] instanceof \DateTimeInterface) {
            $qb->andWhere('eg.lastSeen >= :since')
                ->setParameter('since', $filters['since']);
        }

        // Filtre par recherche textuelle
        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $qb->andWhere('eg.message LIKE :search OR eg.exceptionClass LIKE :search OR eg.file LIKE :search')
                ->setParameter('search', $searchTerm);
        }

        // Filtre par classe d'exception
        if (isset($filters['exception_class'])) {
            $qb->andWhere('eg.exceptionClass = :exceptionClass')
                ->setParameter('exceptionClass', $filters['exception_class']);
        }

        // Filtre par tags
        if (isset($filters['tags']) && !empty($filters['tags'])) {
            if (is_array($filters['tags'])) {
                // Filtrer par plusieurs tags (AND ou OR selon le mode)
                $tagMode = $filters['tag_mode'] ?? 'any'; // 'any' pour OR, 'all' pour AND
                
                if ($tagMode === 'all') {
                    // Mode AND : l'erreur doit avoir TOUS les tags
                    foreach ($filters['tags'] as $index => $tag) {
                        $alias = "t{$index}";
                        $qb->join('eg.tags', $alias);
                        
                        if (is_numeric($tag)) {
                            $qb->andWhere("{$alias}.id = :tag{$index}")
                               ->setParameter("tag{$index}", $tag);
                        } else {
                            $qb->andWhere("{$alias}.name = :tag{$index}")
                               ->setParameter("tag{$index}", $tag);
                        }
                    }
                } else {
                    // Mode OR : l'erreur doit avoir AU MOINS UN des tags
                    $qb->join('eg.tags', 'tags');
                    
                    $tagConditions = [];
                    foreach ($filters['tags'] as $index => $tag) {
                        if (is_numeric($tag)) {
                            $tagConditions[] = "tags.id = :tag{$index}";
                            $qb->setParameter("tag{$index}", $tag);
                        } else {
                            $tagConditions[] = "tags.name = :tag{$index}";
                            $qb->setParameter("tag{$index}", $tag);
                        }
                    }
                    
                    if (!empty($tagConditions)) {
                        $qb->andWhere('(' . implode(' OR ', $tagConditions) . ')');
                    }
                }
            } else {
                // Filtrer par un seul tag
                $qb->join('eg.tags', 'tags');
                if (is_numeric($filters['tags'])) {
                    $qb->andWhere('tags.id = :tag')
                       ->setParameter('tag', $filters['tags']);
                } else {
                    $qb->andWhere('tags.name = :tag')
                       ->setParameter('tag', $filters['tags']);
                }
            }
        }

        // Filtre pour exclure les erreurs sans tags
        if (isset($filters['untagged_only']) && $filters['untagged_only']) {
            $qb->leftJoin('eg.tags', 'untagged_check')
               ->andWhere('untagged_check.id IS NULL');
        }

        // Filtre pour exclure les erreurs ignorées par défaut
        if (!isset($filters['include_ignored']) || !$filters['include_ignored']) {
            $qb->andWhere('eg.status != :ignoredStatus')
                ->setParameter('ignoredStatus', ErrorGroup::STATUS_IGNORED);
        }

        return $qb;
    }

    public function save(ErrorGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ErrorGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
