<?php

namespace App\DataTable\Type;

use App\Entity\User;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\TwigColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;

class UserDataTableType implements DataTableTypeInterface
{
    public function configure(DataTable $dataTable, array $options): void
    {
        $dataTable
            ->add('selection', TwigColumn::class, [
                'label' => ' ',
                'template' => 'admin/datatable/user/user_selection.html.twig',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center selection-cell',
                'render' => function ($value, $context) {
                    return '';
                }
            ])

            ->add('userInfo', TwigColumn::class, [
                'label' => 'Utilisateur',
                'template' => 'admin/datatable/user/user_info.html.twig',
                'searchable' => true,
                'orderable' => true,
                'orderField' => 'u.lastName',
                'className' => 'user-cell',
                'globalSearchable' => true
            ])

            ->add('planInfo', TwigColumn::class, [
                'label' => 'Plan',
                'template' => 'admin/datatable/user/plan_info.html.twig',
                'orderable' => true,
                'orderField' => 'p.name',
                'searchable' => true,
                'className' => 'plan-cell'
            ])

            ->add('usageInfo', TwigColumn::class, [
                'label' => 'Usage',
                'template' => 'admin/datatable/user/usage_info.html.twig',
                'orderable' => false,
                'searchable' => false,
                'className' => 'usage-cell'
            ])

            ->add('createdAt', DateTimeColumn::class, [
                'label' => 'Inscription',
                'format' => 'd/m/Y H:i',
                'orderable' => true,
                'searchable' => false,
                'className' => 'date-cell'
            ])

            ->add('status', TwigColumn::class, [
                'label' => 'Statut',
                'template' => 'admin/datatable/user/status_info.html.twig',
                'orderable' => true,
                'orderField' => 'u.isActive',
                'searchable' => true,
                'className' => 'text-center status-cell'
            ])

            ->add('actions', TwigColumn::class, [
                'label' => 'Actions',
                'template' => 'admin/datatable/user/user_actions.html.twig',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center actions-cell'
            ])

            ->createAdapter(ORMAdapter::class, [
                'entity' => User::class,
                'query' => function ($qb) {
                    $qb->select('u, p')
                        ->from(User::class, 'u')
                        ->leftJoin('u.plan', 'p')
                        ->orderBy('u.createdAt', 'DESC');
                }
            ])

            // Options de configuration
            ->addOrderBy('createdAt', 'DESC')
        ;
    }
}
