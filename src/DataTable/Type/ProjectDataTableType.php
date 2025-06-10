<?php

namespace App\DataTable\Type;

use App\Entity\Project;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\BoolColumn;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\Column\TwigColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;

class ProjectDataTableType implements DataTableTypeInterface
{
    public function configure(DataTable $dataTable, array $options): void
    {
        $dataTable
            ->add('projectInfo', TwigColumn::class, [
                'label' => 'Projet',
                'template' => 'projects/datatable/project_info.html.twig',
                'searchable' => true,
                'orderable' => true,
                'orderField' => 'p.name',
                'className' => 'project-cell'
            ])
            ->add('environment', TextColumn::class, [
                'label' => 'Environnement',
                'field' => 'p.environment',
                'render' => function ($value) {
                    if ($value) {
                        return '<span class="status-badge badge-info">' . htmlspecialchars($value) . '</span>';
                    }
                    return '<span class="text-muted">-</span>';
                },
                'raw' => true,
                'searchable' => true,
                'orderable' => true,
                'className' => 'text-center environment-cell'
            ])
            ->add('isActive', BoolColumn::class, [
                'label' => 'Statut',
                'trueValue' => '<span class="project-status active">Actif</span>',
                'falseValue' => '<span class="project-status inactive">Inactif</span>',
                'nullValue' => '<span class="project-status inactive">Inactif</span>',
                'orderable' => true,
                'searchable' => true,
                'className' => 'text-center status-cell'
            ])
            ->add('errorStats', TwigColumn::class, [
                'label' => 'Erreurs',
                'template' => 'projects/datatable/error_stats.html.twig',
                'orderable' => true,
                'orderField' => 'p.totalErrors',
                'searchable' => false,
                'className' => 'text-center errors-cell'
            ])
            ->add('lastErrorAt', DateTimeColumn::class, [
                'label' => 'Dernière activité',
                'field' => 'p.lastErrorAt',
                'format' => 'd/m/Y H:i',
                'nullValue' => '<span class="text-muted">Aucune erreur</span>',
                'orderable' => true,
                'searchable' => false,
                'className' => 'text-center activity-cell'
            ])
            ->add('createdAt', DateTimeColumn::class, [
                'label' => 'Créé le',
                'field' => 'p.createdAt',
                'format' => 'd/m/Y',
                'orderable' => true,
                'searchable' => false,
                'className' => 'text-center created-cell'
            ])
            ->add('actions', TwigColumn::class, [
                'label' => 'Actions',
                'template' => 'projects/datatable/project_actions.html.twig',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center actions-cell'
            ])
            ->createAdapter(ORMAdapter::class, [
                'entity' => Project::class,
                'query' => function ($qb) use ($options) {
                    $qb->select('p')
                        ->from(Project::class, 'p')
                        ->where('p.owner = :owner')
                        ->setParameter('owner', $options['user'])
                        ->orderBy('p.createdAt', 'DESC');
                }
            ])
        ;
    }
}