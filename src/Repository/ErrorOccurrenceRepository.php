<?php

namespace App\Repository;

use App\Entity\ErrorGroup;
use App\Entity\ErrorOccurrence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ErrorOccurrence>
 *
 * @method ErrorOccurrence|null find($id, $lockMode = null, $lockVersion = null)
 * @method ErrorOccurrence|null findOneBy(array $criteria, array $orderBy = null)
 * @method ErrorOccurrence[]    findAll()
 * @method ErrorOccurrence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ErrorOccurrenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ErrorOccurrence::class);
    }

    /**
     * Trouve les occurrences d'un groupe d'erreur avec pagination
     */
    public function findByErrorGroup(
        ErrorGroup $errorGroup,
        ?int $limit = null,
        ?int $offset = null,
        string $sortDirection = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('eo');
        $qb->where('eo.errorGroup = :errorGroup')
            ->setParameter('errorGroup', $errorGroup)
            ->orderBy('eo.createdAt', $sortDirection);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les occurrences d'un groupe d'erreur
     */
    public function countByErrorGroup(ErrorGroup $errorGroup): int
    {
        $qb = $this->createQueryBuilder('eo');
        $qb->select('COUNT(eo.id)')
            ->where('eo.errorGroup = :errorGroup')
            ->setParameter('errorGroup', $errorGroup);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Compte les occurrences avec filtres
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->select('COUNT(eo.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Statistiques d'occurrences pour un groupe d'erreur par jour
     */
    public function getOccurrenceStatsForGroup(ErrorGroup $errorGroup, int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");
        $startDate->setTime(0, 0, 0);

        // Utiliser SQL natif pour plus de flexibilité
        $sql = "
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM error_occurrences 
            WHERE error_group_id = :errorGroupId 
            AND created_at >= :startDate
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'errorGroupId' => $errorGroup->getId(),
            'startDate' => $startDate->format('Y-m-d H:i:s')
        ]);

        $results = $result->fetchAllAssociative();

        // Remplir les jours manquants avec 0
        return $this->fillMissingDays($results, $days);
    }

    /**
     * Tendance des erreurs sur X jours
     */
    public function getErrorTrend(int $days = 7, array $filters = []): array
    {
        $startDate = new \DateTime("-{$days} days");
        $startDate->setTime(0, 0, 0);

        // Construire la requête SQL avec les filtres
        $sql = "
            SELECT DATE(eo.created_at) as date, COUNT(*) as count
            FROM error_occurrences eo
            JOIN error_groups eg ON eo.error_group_id = eg.id
        ";

        $params = ['startDate' => $startDate->format('Y-m-d H:i:s')];
        $whereClauses = ['eo.created_at >= :startDate'];

        // Filtre utilisateur (multi-tenancy) via projects
        if (isset($filters['user'])) {
            $sql .= " LEFT JOIN projects p ON eg.project_id = p.id";
            // Exclure les erreurs sans projet pour éviter les données orphelines
            $whereClauses[] = 'p.owner_id = :userId AND eg.project_id IS NOT NULL';
            $params['userId'] = $filters['user']->getId();
        }

        // Ajouter les autres filtres
        if (isset($filters['project'])) {
            $whereClauses[] = 'eg.project = :project';
            $params['project'] = $filters['project'];
        }

        if (isset($filters['status'])) {
            $whereClauses[] = 'eg.status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['http_status'])) {
            $whereClauses[] = 'eg.http_status_code = :httpStatus';
            $params['httpStatus'] = $filters['http_status'];
        }

        if (isset($filters['error_type'])) {
            $whereClauses[] = 'eg.error_type = :errorType';
            $params['errorType'] = $filters['error_type'];
        }

        if (isset($filters['environment'])) {
            $whereClauses[] = 'eo.environment = :environment';
            $params['environment'] = $filters['environment'];
        }

        $sql .= " WHERE " . implode(' AND ', $whereClauses);
        $sql .= " GROUP BY DATE(eo.created_at) ORDER BY date ASC";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);

        $results = $result->fetchAllAssociative();

        return $this->fillMissingDays($results, $days);
    }

    /**
     * Occurrences par heure pour aujourd'hui
     */
    public function getTodayHourlyStats(array $filters = []): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        // Utiliser SQL natif pour HOUR() avec isolation utilisateur
        $sql = "
            SELECT HOUR(eo.created_at) as hour, COUNT(*) as count
            FROM error_occurrences eo
            JOIN error_groups eg ON eo.error_group_id = eg.id
        ";

        $params = [
            'today' => $today->format('Y-m-d H:i:s'),
            'tomorrow' => $tomorrow->format('Y-m-d H:i:s')
        ];
        $whereClauses = ['eo.created_at >= :today AND eo.created_at < :tomorrow'];

        // Filtre utilisateur (multi-tenancy)
        if (isset($filters['user'])) {
            $sql .= " LEFT JOIN projects p ON eg.project_id = p.id";
            $whereClauses[] = 'p.owner_id = :userId AND eg.project_id IS NOT NULL';
            $params['userId'] = $filters['user']->getId();
        }

        $sql .= " WHERE " . implode(' AND ', $whereClauses);
        $sql .= " GROUP BY HOUR(eo.created_at) ORDER BY hour ASC";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);

        $results = $result->fetchAllAssociative();

        // Remplir les heures manquantes avec 0
        $hours = [];
        for ($i = 0; $i < 24; $i++) {
            $hours[$i] = 0;
        }

        foreach ($results as $result) {
            $hours[(int)$result['hour']] = (int)$result['count'];
        }

        return array_map(function($hour, $count) {
            return ['hour' => $hour, 'count' => $count];
        }, array_keys($hours), $hours);
    }

    /**
     * Top des URLs avec le plus d'erreurs
     */
    public function getTopUrlsByErrors(int $limit = 10, array $filters = []): array
    {
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->select('eo.url, COUNT(eo.id) as error_count')
            ->where('eo.url IS NOT NULL')
            ->groupBy('eo.url')
            ->orderBy('error_count', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Top des adresses IP avec le plus d'erreurs
     */
    public function getTopIpsByErrors(int $limit = 10, array $filters = []): array
    {
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->select('eo.ipAddress, COUNT(eo.id) as error_count')
            ->where('eo.ipAddress IS NOT NULL')
            ->groupBy('eo.ipAddress')
            ->orderBy('error_count', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Occurrences par environnement
     */
    public function getStatsByEnvironment(array $filters = []): array
    {
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->select('eo.environment, COUNT(eo.id) as count')
            ->where('eo.environment IS NOT NULL')
            ->groupBy('eo.environment')
            ->orderBy('count', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Occurrences récentes avec détails
     */
    public function getRecentOccurrences(int $limit = 20, array $filters = []): array
    {
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->leftJoin('eo.errorGroup', 'eg')
            ->addSelect('eg')
            ->orderBy('eo.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques de performance (mémoire, temps d'exécution)
     */
    public function getPerformanceStats(array $filters = []): array
    {
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->select('
                AVG(eo.memoryUsage) as avg_memory,
                MAX(eo.memoryUsage) as max_memory,
                AVG(eo.executionTime) as avg_execution_time,
                MAX(eo.executionTime) as max_execution_time,
                COUNT(eo.id) as total_occurrences
            ')
            ->where('eo.memoryUsage IS NOT NULL OR eo.executionTime IS NOT NULL');

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Nettoie les anciennes occurrences
     */
    public function cleanupOldOccurrences(int $daysOld = 90): int
    {
        $date = new \DateTime("-{$daysOld} days");

        $qb = $this->createQueryBuilder('eo');
        $qb->delete()
            ->where('eo.createdAt < :date')
            ->setParameter('date', $date);

        return $qb->getQuery()->execute();
    }

    /**
     * Trouve les occurrences d'un utilisateur spécifique
     */
    public function findByUserId(string $userId, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('eo');
        $qb->leftJoin('eo.errorGroup', 'eg')
            ->addSelect('eg')
            ->where('eo.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('eo.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les occurrences par IP
     */
    public function findByIpAddress(string $ipAddress, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('eo');
        $qb->leftJoin('eo.errorGroup', 'eg')
            ->addSelect('eg')
            ->where('eo.ipAddress = :ipAddress')
            ->setParameter('ipAddress', $ipAddress)
            ->orderBy('eo.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Créé un QueryBuilder avec filtres appliqués
     */
    private function createFilteredQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('eo');

        // TOUJOURS joindre ErrorGroup pour pouvoir filtrer par utilisateur
        $qb->join('eo.errorGroup', 'eg');

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

        // Filtre par statut du groupe
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
            $qb->andWhere('eo.environment = :environment')
                ->setParameter('environment', $filters['environment']);
        }

        // Filtre par date (depuis)
        if (isset($filters['since']) && $filters['since'] instanceof \DateTimeInterface) {
            $qb->andWhere('eo.createdAt >= :since')
                ->setParameter('since', $filters['since']);
        }

        // Filtre par utilisateur
        if (isset($filters['user_id'])) {
            $qb->andWhere('eo.userId = :userId')
                ->setParameter('userId', $filters['user_id']);
        }

        // Filtre par IP
        if (isset($filters['ip_address'])) {
            $qb->andWhere('eo.ipAddress = :ipAddress')
                ->setParameter('ipAddress', $filters['ip_address']);
        }

        return $qb;
    }

    /**
     * Remplit les jours manquants avec des valeurs à 0
     */
    private function fillMissingDays(array $results, int $days): array
    {
        $filledResults = [];
        $resultsMap = [];

        // Créer un map des résultats existants
        foreach ($results as $result) {
            $resultsMap[$result['date']] = (int)$result['count'];
        }

        // Générer tous les jours et remplir avec 0 si manquant
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} days");
            $dateStr = $date->format('Y-m-d');

            $filledResults[] = [
                'date' => $dateStr,
                'count' => $resultsMap[$dateStr] ?? 0
            ];
        }

        return $filledResults;
    }

    public function save(ErrorOccurrence $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ErrorOccurrence $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
