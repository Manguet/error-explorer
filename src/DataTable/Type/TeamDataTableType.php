<?php

namespace App\DataTable\Type;

use App\Entity\Team;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\NumberColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\Column\TwigColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;

class TeamDataTableType implements DataTableTypeInterface
{
    public function configure(DataTable $dataTable, array $options): void
    {
        $dataTable
            ->add('name', TextColumn::class, [
                'label' => 'Nom',
                'field' => 't.name',
                'searchable' => true,
                'orderable' => true,
                'render' => function ($value, Team $team) {
                    return sprintf(
                        '<div class="d-flex align-items-center">
                            <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                <i class="fas fa-users text-white"></i>
                            </div>
                            <div>
                                <div class="fw-bold">%s</div>
                                <small class="text-muted">%s</small>
                            </div>
                        </div>',
                        htmlspecialchars($team->getName()),
                        htmlspecialchars($team->getSlug())
                    );
                }
            ])
            ->add('owner', TextColumn::class, [
                'label' => 'Propriétaire',
                'field' => 'owner.firstName',
                'searchable' => true,
                'orderable' => true,
                'render' => function ($value, Team $team) {
                    $owner = $team->getOwner();
                    return sprintf(
                        '<div>
                            <div class="fw-bold">%s</div>
                            <small class="text-muted">%s</small>
                        </div>',
                        htmlspecialchars($owner->getFullName()),
                        htmlspecialchars($owner->getEmail())
                    );
                }
            ])
            ->add('memberCount', NumberColumn::class, [
                'label' => 'Membres',
                'field' => 'memberCount',
                'searchable' => false,
                'orderable' => true,
                'render' => function ($value, Team $team) {
                    $count = $team->getMembersCount();
                    $max = $team->getMaxMembers();
                    $percentage = $max > 0 ? round(($count / $max) * 100) : 0;
                    
                    $badgeClass = $percentage >= 90 ? 'bg-danger' : ($percentage >= 70 ? 'bg-warning' : 'bg-success');
                    
                    return sprintf(
                        '<div class="d-flex align-items-center">
                            <span class="badge %s me-2">%d/%d</span>
                            <div class="progress flex-grow-1" style="height: 6px;">
                                <div class="progress-bar %s" style="width: %d%%"></div>
                            </div>
                        </div>',
                        $badgeClass,
                        $count,
                        $max,
                        str_replace('bg-', 'bg-', $badgeClass),
                        $percentage
                    );
                }
            ])
            ->add('projectCount', NumberColumn::class, [
                'label' => 'Projets',
                'field' => 'projectCount',
                'searchable' => false,
                'orderable' => true,
                'render' => function ($value, Team $team) {
                    $count = $team->getProjectsCount();
                    $max = $team->getMaxProjects();
                    $percentage = $max > 0 ? round(($count / $max) * 100) : 0;
                    
                    $badgeClass = $percentage >= 90 ? 'bg-danger' : ($percentage >= 70 ? 'bg-warning' : 'bg-success');
                    
                    return sprintf(
                        '<div class="d-flex align-items-center">
                            <span class="badge %s me-2">%d/%d</span>
                            <div class="progress flex-grow-1" style="height: 6px;">
                                <div class="progress-bar %s" style="width: %d%%"></div>
                            </div>
                        </div>',
                        $badgeClass,
                        $count,
                        $max,
                        str_replace('bg-', 'bg-', $badgeClass),
                        $percentage
                    );
                }
            ])
            ->add('status', TwigColumn::class, [
                'label' => 'Statut',
                'template' => 'admin/datatable/team/status.html.twig',
                'searchable' => false,
                'orderable' => false,
            ])
            ->add('createdAt', DateTimeColumn::class, [
                'label' => 'Créée le',
                'field' => 't.createdAt',
                'format' => 'd/m/Y H:i',
                'searchable' => false,
                'orderable' => true,
            ])
            ->add('actions', TwigColumn::class, [
                'label' => 'Actions',
                'template' => 'admin/datatable/team/actions.html.twig',
                'searchable' => false,
                'orderable' => false,
            ])
            ->createAdapter(ORMAdapter::class, [
                'entity' => Team::class,
                'query' => function (QueryBuilder $builder) {
                    $builder
                        ->select('t', 'owner', 'COUNT(DISTINCT m.id) as memberCount', 'COUNT(DISTINCT p.id) as projectCount')
                        ->from(Team::class, 't')
                        ->leftJoin('t.owner', 'owner')
                        ->leftJoin('t.members', 'm')
                        ->leftJoin('t.projects', 'p')
                        ->groupBy('t.id');
                },
            ]);
    }
}