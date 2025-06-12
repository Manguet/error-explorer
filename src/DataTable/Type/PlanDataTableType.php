<?php

namespace App\DataTable\Type;

use App\Entity\Plan;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\BoolColumn;
use Omines\DataTablesBundle\Column\TwigColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;

class PlanDataTableType implements DataTableTypeInterface
{
    public function configure(DataTable $dataTable, array $options): void
    {
        $dataTable
            ->add('planInfo', TwigColumn::class, [
                'label' => 'Plan',
                'template' => 'admin/datatable/plan/plan_info.html.twig',
                'searchable' => true,
                'orderable' => true,
                'orderField' => 'p.name',
                'className' => 'plan-cell'
            ])
            ->add('priceInfo', TwigColumn::class, [
                'label' => 'Prix',
                'template' => 'admin/datatable/plan/price_info.html.twig',
                'orderable' => true,
                'orderField' => 'p.priceMonthly',
                'searchable' => false,
                'className' => 'price-cell'
            ])
            ->add('limitsInfo', TwigColumn::class, [
                'label' => 'Limites',
                'template' => 'admin/datatable/plan/limits_info.html.twig',
                'orderable' => false,
                'searchable' => false,
                'className' => 'limits-cell'
            ])
            ->add('featuresInfo', TwigColumn::class, [
                'label' => 'Fonctionnalités',
                'template' => 'admin/datatable/plan/features_info.html.twig',
                'orderable' => false,
                'searchable' => false,
                'className' => 'features-cell'
            ])
            ->add('isActive', BoolColumn::class, [
                'label' => 'Statut',
                'trueValue' => '<span class="badge badge-success">Actif</span>',
                'falseValue' => '<span class="badge badge-secondary">Inactif</span>',
                'nullValue' => '<span class="badge badge-secondary">Inactif</span>',
                'orderable' => true,
                'searchable' => true,
                'className' => 'text-center status-cell'
            ])
            ->add('actions', TwigColumn::class, [
                'label' => 'Actions',
                'template' => 'admin/datatable/plan/plan_actions.html.twig',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center actions-cell'
            ])
            ->createAdapter(ORMAdapter::class, [
                'entity' => Plan::class,
                'query' => function ($qb) {
                    $qb->select('p')
                        ->from(Plan::class, 'p')
                        ->leftJoin('App\Entity\User', 'u', 'WITH', 'u.plan = p.id')
                        ->groupBy('p.id')
                        ->orderBy('p.sortOrder', 'ASC');
                }
            ])
        ;
    }
}
