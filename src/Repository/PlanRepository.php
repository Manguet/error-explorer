<?php

namespace App\Repository;

use App\Entity\Plan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plan>
 */
class PlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    /**
     * Trouve tous les plans actifs triés par ordre d'affichage
     */
    public function findActivePlans(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.isBuyable = true')
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.priceMonthly', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un plan par son slug
     */
    public function findBySlug(string $slug): ?Plan
    {
        return $this->findOneBy(['slug' => $slug, 'isActive' => true]);
    }

    /**
     * Trouve le plan gratuit (prix = 0)
     */
    public function findFreePlan(): ?Plan
    {
        return $this->createQueryBuilder('p')
            ->where('p.priceMonthly = 0')
            ->andWhere('p.isActive = true')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les plans populaires
     */
    public function findPopularPlans(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPopular = true')
            ->andWhere('p.isActive = true')
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un slug existe déjà
     */
    public function isSlugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Statistiques des plans
     */
    public function getPlansStats(): array
    {
        try {
            // Récupérer tous les plans actifs
            $plans = $this->findBy(['isActive' => true], ['sortOrder' => 'ASC']);

            $userStats = [];
            $revenueStats = [];

            foreach ($plans as $plan) {
                // Compter les utilisateurs pour ce plan
                $userCount = $this->getEntityManager()->createQuery('
                    SELECT COUNT(u.id)
                    FROM App\Entity\User u
                    WHERE u.plan = :plan AND u.isActive = true
                ')
                ->setParameter('plan', $plan)
                ->getSingleScalarResult();

                $userStats[] = [
                    'name' => $plan->getName(),
                    'slug' => $plan->getSlug(),
                    'user_count' => (int) $userCount
                ];

                $potentialRevenue = (float) $plan->getPriceMonthly() * (int) $userCount;

                $revenueStats[] = [
                    'name' => $plan->getName(),
                    'priceMonthly' => (float) $plan->getPriceMonthly(),
                    'user_count' => (int) $userCount,
                    'potential_revenue' => $potentialRevenue
                ];
            }

            // Trier les revenus par potentiel décroissant
            usort($revenueStats, function($a, $b) {
                return $b['potential_revenue'] <=> $a['potential_revenue'];
            });

            return [
                'user_distribution' => $userStats,
                'revenue_potential' => $revenueStats
            ];
        } catch (\Exception $e) {
            // En cas d'erreur, retourner des statistiques vides avec la structure attendue
            return [
                'user_distribution' => [],
                'revenue_potential' => []
            ];
        }
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
        $baseSlug = substr($baseSlug, 0, 90); // Laisser de la place pour le suffixe

        $slug = $baseSlug;
        $counter = 1;

        while ($this->isSlugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function save(Plan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Plan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
