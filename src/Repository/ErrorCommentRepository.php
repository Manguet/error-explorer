<?php

namespace App\Repository;

use App\Entity\ErrorComment;
use App\Entity\ErrorGroup;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ErrorComment>
 */
class ErrorCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ErrorComment::class);
    }

    /**
     * Récupère tous les commentaires d'un groupe d'erreur, ordonnés par date
     */
    public function findByErrorGroup(ErrorGroup $errorGroup, bool $includeInternal = true): array
    {
        $qb = $this->createQueryBuilder('ec')
            ->leftJoin('ec.author', 'author')
            ->addSelect('author')
            ->where('ec.errorGroup = :errorGroup')
            ->setParameter('errorGroup', $errorGroup)
            ->orderBy('ec.createdAt', 'ASC');

        if (!$includeInternal) {
            $qb->andWhere('ec.isInternal = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les commentaires principaux (sans parent) d'un groupe d'erreur
     */
    public function findMainCommentsByErrorGroup(ErrorGroup $errorGroup, bool $includeInternal = true): array
    {
        $qb = $this->createQueryBuilder('ec')
            ->leftJoin('ec.author', 'author')
            ->addSelect('author')
            ->where('ec.errorGroup = :errorGroup')
            ->andWhere('ec.parent IS NULL')
            ->setParameter('errorGroup', $errorGroup)
            ->orderBy('ec.createdAt', 'ASC');

        if (!$includeInternal) {
            $qb->andWhere('ec.isInternal = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les réponses à un commentaire parent
     */
    public function findRepliesByParent(ErrorComment $parent): array
    {
        return $this->createQueryBuilder('ec')
            ->leftJoin('ec.author', 'author')
            ->addSelect('author')
            ->where('ec.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('ec.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de commentaires d'un groupe d'erreur
     */
    public function countByErrorGroup(ErrorGroup $errorGroup, bool $includeInternal = true): int
    {
        $qb = $this->createQueryBuilder('ec')
            ->select('COUNT(ec.id)')
            ->where('ec.errorGroup = :errorGroup')
            ->setParameter('errorGroup', $errorGroup);

        if (!$includeInternal) {
            $qb->andWhere('ec.isInternal = false');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les commentaires récents d'un utilisateur
     */
    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('ec')
            ->leftJoin('ec.errorGroup', 'eg')
            ->leftJoin('eg.projectEntity', 'p')
            ->addSelect('eg', 'p')
            ->where('ec.author = :user')
            ->setParameter('user', $user)
            ->orderBy('ec.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche dans les commentaires
     */
    public function search(string $query, ErrorGroup $errorGroup = null, User $user = null): array
    {
        $qb = $this->createQueryBuilder('ec')
            ->leftJoin('ec.author', 'author')
            ->leftJoin('ec.errorGroup', 'eg')
            ->addSelect('author', 'eg')
            ->where('ec.content LIKE :query')
            ->setParameter('query', '%' . $query . '%');

        if ($errorGroup) {
            $qb->andWhere('ec.errorGroup = :errorGroup')
               ->setParameter('errorGroup', $errorGroup);
        }

        if ($user) {
            $qb->andWhere('ec.author = :user')
               ->setParameter('user', $user);
        }

        return $qb->orderBy('ec.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les commentaires avec pièces jointes
     */
    public function findWithAttachments(ErrorGroup $errorGroup = null): array
    {
        $qb = $this->createQueryBuilder('ec')
            ->leftJoin('ec.author', 'author')
            ->leftJoin('ec.errorGroup', 'eg')
            ->addSelect('author', 'eg')
            ->where('ec.attachments IS NOT NULL');

        if ($errorGroup) {
            $qb->andWhere('ec.errorGroup = :errorGroup')
               ->setParameter('errorGroup', $errorGroup);
        }

        return $qb->orderBy('ec.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Supprime tous les commentaires d'un groupe d'erreur
     */
    public function deleteByErrorGroup(ErrorGroup $errorGroup): int
    {
        return $this->createQueryBuilder('ec')
            ->delete()
            ->where('ec.errorGroup = :errorGroup')
            ->setParameter('errorGroup', $errorGroup)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère les statistiques des commentaires pour un projet
     */
    public function getStatsForProject(User $projectOwner): array
    {
        return $this->createQueryBuilder('ec')
            ->select([
                'COUNT(ec.id) as total_comments',
                'COUNT(DISTINCT ec.author) as unique_authors',
                'COUNT(DISTINCT ec.errorGroup) as commented_errors',
                'AVG(LENGTH(ec.content)) as avg_length'
            ])
            ->leftJoin('ec.errorGroup', 'eg')
            ->leftJoin('eg.projectEntity', 'p')
            ->where('p.owner = :owner')
            ->setParameter('owner', $projectOwner)
            ->getQuery()
            ->getSingleResult();
    }
}