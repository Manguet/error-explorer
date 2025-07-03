<?php

namespace App\DataTable\Type;

use App\Entity\ErrorOccurrence;
use App\Repository\ErrorOccurrenceRepository;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\Column\TwigColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;

class ErrorOccurrenceDataTableType implements DataTableTypeInterface
{
    public function configure(DataTable $dataTable, array $options): void
    {
        $errorGroup = $options['error_group'] ?? null;
        $user = $options['user'] ?? null;

        if (!$errorGroup || !$user) {
            throw new \InvalidArgumentException('ErrorGroup and User are required');
        }

        $dataTable
            ->add('createdAt', DateTimeColumn::class, [
                'label' => 'Date/Heure',
                'format' => 'd/m/Y H:i:s',
                'searchable' => false,
                'className' => 'text-sm font-mono',
                'render' => function ($value, ErrorOccurrence $occurrence) {
                    return $occurrence->getCreatedAt()->format('d/m/Y H:i:s');
                }
            ])
            
            ->add('environment', TextColumn::class, [
                'label' => 'Environnement',
                'propertyPath' => 'environment',
                'searchable' => true,
                'className' => 'text-sm',
                'render' => function ($value, ErrorOccurrence $occurrence) {
                    $env = $occurrence->getEnvironment() ?: 'unknown';
                    $badgeClass = match($env) {
                        'production', 'prod' => 'badge-danger',
                        'staging' => 'badge-warning',
                        'development', 'dev' => 'badge-success',
                        'test' => 'badge-info',
                        default => 'badge-secondary'
                    };
                    return sprintf('<span class="status-badge %s">%s</span>', $badgeClass, ucfirst($env));
                }
            ])

            ->add('request_info', TwigColumn::class, [
                'label' => 'Requête',
                'template' => 'dashboard/datatable/occurrence_request.html.twig',
                'searchable' => false,
                'orderable' => false,
                'className' => 'text-sm'
            ])

            ->add('context_info', TwigColumn::class, [
                'label' => 'Contexte',
                'template' => 'dashboard/datatable/occurrence_context.html.twig',
                'searchable' => false,
                'orderable' => false,
                'className' => 'text-sm'
            ])

            ->add('performance', TwigColumn::class, [
                'label' => 'Performance',
                'template' => 'dashboard/datatable/occurrence_performance.html.twig',
                'searchable' => false,
                'orderable' => false,
                'className' => 'text-sm text-right'
            ])

            ->add('actions', TwigColumn::class, [
                'label' => 'Actions',
                'template' => 'dashboard/datatable/occurrence_actions.html.twig',
                'searchable' => false,
                'orderable' => false,
                'className' => 'text-center',
                'globalSearchable' => false
            ])

            ->createAdapter(ORMAdapter::class, [
                'entity' => ErrorOccurrence::class,
                'query' => function (QueryBuilder $qb) use ($errorGroup) {
                    $qb->select('o')
                       ->from(ErrorOccurrence::class, 'o')
                       ->where('o.errorGroup = :errorGroup')
                       ->setParameter('errorGroup', $errorGroup)
                       ->orderBy('o.createdAt', 'DESC');
                }
            ])

        ;
    }
}