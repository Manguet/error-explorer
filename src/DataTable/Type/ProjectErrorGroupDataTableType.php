<?php

namespace App\DataTable\Type;

use App\Entity\ErrorGroup;
use App\Repository\ErrorGroupRepository;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\NumberColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\Column\TwigColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;

class ProjectErrorGroupDataTableType implements DataTableTypeInterface
{
    public function configure(DataTable $dataTable, array $options): void
    {
        $user = $options['user'] ?? null;
        $project = $options['project'] ?? null;
        $filters = $options['filters'] ?? [];

        $dataTable
            ->add('select', TwigColumn::class, [
                'label' => 'Sélection',
                'template' => 'dashboard/datatable/error_select.html.twig',
                'searchable' => false,
                'orderable' => false,
                'className' => 'table-cell-select',
                'render' => function ($value, $context) {
                    return '';  // Le contenu est géré par le template
                }
            ])
            ->add('error', TwigColumn::class, [
                'label' => 'Erreur',
                'template' => 'dashboard/datatable/project_error_info.html.twig',
                'searchable' => true,
                'orderable' => true,
                'orderField' => 'eg.message',
                'className' => 'table-cell-main',
            ])
            ->add('errorType', TwigColumn::class, [
                'label' => 'Type',
                'template' => 'dashboard/datatable/error_type.html.twig',
                'searchable' => false,
                'orderable' => true,
                'orderField' => 'eg.errorType',
                'className' => 'table-cell-center',
            ])
            ->add('status', TwigColumn::class, [
                'label' => 'Statut',
                'template' => 'dashboard/datatable/error_status.html.twig',
                'searchable' => false,
                'orderable' => true,
                'orderField' => 'eg.status',
                'className' => 'table-cell-center',
            ])
            ->add('occurrenceCount', NumberColumn::class, [
                'label' => 'Occurrences',
                'searchable' => false,
                'orderable' => true,
                'orderField' => 'eg.occurrenceCount',
                'className' => 'table-cell-center',
                'render' => function ($value, ErrorGroup $errorGroup) {
                    return sprintf(
                        '<div class="table-cell-title">%s</div><div class="table-cell-meta">Depuis %s</div>',
                        number_format($value),
                        $errorGroup->getFirstSeen()->format('d/m/Y')
                    );
                }
            ])
            ->add('assignedTo', TwigColumn::class, [
                'label' => 'Assigné à',
                'template' => 'dashboard/datatable/error_assignment.html.twig',
                'searchable' => false,
                'orderable' => true,
                'orderField' => 'assigned_user.firstName',
                'className' => 'table-cell-center',
            ])
            ->add('lastSeen', DateTimeColumn::class, [
                'label' => 'Dernière vue',
                'format' => 'd/m H:i',
                'searchable' => false,
                'orderable' => true,
                'orderField' => 'eg.lastSeen',
                'className' => 'table-cell-center',
                'render' => function ($value, ErrorGroup $errorGroup) {
                    return sprintf(
                        '<div class="table-cell-title">%s</div><div class="table-cell-meta">%s</div>',
                        $errorGroup->getLastSeen()->format('d/m H:i'),
                        $errorGroup->getLastSeen()->format('d/m/Y')
                    );
                }
            ])
            ->add('actions', TwigColumn::class, [
                'label' => 'Actions',
                'template' => 'dashboard/datatable/project_error_actions.html.twig',
                'searchable' => false,
                'orderable' => false,
                'className' => 'table-cell-actions',
            ])
            ->createAdapter(ORMAdapter::class, [
                'entity' => ErrorGroup::class,
                'query' => function ($qb) use ($user, $project, $filters) {
                    $qb->select('eg', 'assigned_user')
                        ->from(ErrorGroup::class, 'eg')
                        ->leftJoin('eg.assignedTo', 'assigned_user');

                    // Filtrer directement par projet (plus performant)
                    // La sécurité est déjà gérée par le controller qui vérifie que le projet appartient à l'utilisateur
                    if ($project) {
                        $qb->where('eg.project = :project')
                           ->setParameter('project', $project);
                    }

                    // Appliquer les autres filtres
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

                    $qb->orderBy('eg.lastSeen', 'DESC');
                }
            ])
            ->addOrderBy('lastSeen', 'DESC');
    }
}