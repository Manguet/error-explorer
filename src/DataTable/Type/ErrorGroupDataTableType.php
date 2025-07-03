<?php

namespace App\DataTable\Type;

use App\Entity\ErrorGroup;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\NumberColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\Column\TwigColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;

class ErrorGroupDataTableType implements DataTableTypeInterface
{
    public function configure(DataTable $dataTable, array $options): void
    {
        $user = $options['user'] ?? null;
        $filters = $options['filters'] ?? [];

        $dataTable
            ->add('errorInfo', TwigColumn::class, [
                'label' => 'Erreur',
                'template' => 'dashboard/datatable/error_info.html.twig',
                'searchable' => true,
                'orderable' => true,
                'orderField' => 'eg.message',
                'className' => 'error-cell',
                'globalSearchable' => true
            ])

            ->add('project', TextColumn::class, [
                'label' => 'Projet',
                'field' => 'eg.project',
                'searchable' => true,
                'orderable' => true,
                'className' => 'project-cell',
                'render' => function ($value, $context) {
                    $project = $context->getProject();
                    return sprintf(
                        '<a href="/dashboard/project/%s" class="table-cell-title" style="color: #3b82f6; text-decoration: none; font-weight: 500;">%s</a>',
                        htmlspecialchars($project),
                        htmlspecialchars($project)
                    );
                }
            ])

            ->add('status', TwigColumn::class, [
                'label' => 'Statut',
                'template' => 'dashboard/datatable/status_badge.html.twig',
                'orderable' => true,
                'orderField' => 'eg.status',
                'searchable' => true,
                'className' => 'text-center status-cell'
            ])

            ->add('occurrenceInfo', TwigColumn::class, [
                'label' => 'Occurrences',
                'template' => 'dashboard/datatable/occurrence_info.html.twig',
                'orderable' => true,
                'orderField' => 'eg.occurrenceCount',
                'searchable' => false,
                'className' => 'occurrence-cell'
            ])

            ->add('lastSeen', DateTimeColumn::class, [
                'label' => 'Dernière vue',
                'format' => 'd/m H:i',
                'orderable' => true,
                'searchable' => false,
                'className' => 'date-cell'
            ])

            ->add('actions', TwigColumn::class, [
                'label' => 'Actions',
                'template' => 'dashboard/datatable/error_actions.html.twig',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center actions-cell'
            ])

            ->createAdapter(ORMAdapter::class, [
                'entity' => ErrorGroup::class,
                'query' => function ($qb) use ($user, $filters) {
                    $qb->select('eg, p')
                        ->from(ErrorGroup::class, 'eg')
                        ->join('eg.projectEntity', 'p');

                    // IMPORTANT: Filtrer par utilisateur (multi-tenancy)
                    if ($user) {
                        $qb->andWhere('p.owner = :user')
                           ->setParameter('user', $user);
                    }

                    // Appliquer les filtres depuis la requête
                    if (isset($filters['project']) && !empty($filters['project'])) {
                        $qb->andWhere('eg.project = :project')
                           ->setParameter('project', $filters['project']);
                    }

                    if (isset($filters['status']) && !empty($filters['status'])) {
                        $qb->andWhere('eg.status = :status')
                           ->setParameter('status', $filters['status']);
                    }

                    if (isset($filters['http_status']) && !empty($filters['http_status'])) {
                        $qb->andWhere('eg.httpStatusCode = :httpStatus')
                           ->setParameter('httpStatus', $filters['http_status']);
                    }

                    if (isset($filters['error_type']) && !empty($filters['error_type'])) {
                        $qb->andWhere('eg.errorType = :errorType')
                           ->setParameter('errorType', $filters['error_type']);
                    }

                    if (isset($filters['environment']) && !empty($filters['environment'])) {
                        $qb->andWhere('eg.environment = :environment')
                           ->setParameter('environment', $filters['environment']);
                    }

                    if (isset($filters['since']) && $filters['since'] instanceof \DateTimeInterface) {
                        $qb->andWhere('eg.lastSeen >= :since')
                           ->setParameter('since', $filters['since']);
                    }

                    if (isset($filters['search']) && !empty($filters['search'])) {
                        $searchTerm = '%' . $filters['search'] . '%';
                        $qb->andWhere('eg.message LIKE :search OR eg.exceptionClass LIKE :search OR eg.file LIKE :search')
                           ->setParameter('search', $searchTerm);
                    }

                    // Exclure les erreurs ignorées par défaut
                    if (!isset($filters['include_ignored']) || !$filters['include_ignored']) {
                        $qb->andWhere('eg.status != :ignoredStatus')
                           ->setParameter('ignoredStatus', ErrorGroup::STATUS_IGNORED);
                    }

                    $qb->orderBy('eg.lastSeen', 'DESC');
                }
            ]);
    }
}
