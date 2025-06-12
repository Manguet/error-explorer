<?php

namespace App\DataTable\Type;

use App\Entity\TeamMember;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\Column\TwigColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;

class TeamMemberDataTableType implements DataTableTypeInterface
{
    public function configure(DataTable $dataTable, array $options): void
    {
        $team = $options['team'] ?? null;
        
        $dataTable
            ->add('user', TextColumn::class, [
                'label' => 'Membre',
                'field' => 'user.firstName',
                'searchable' => true,
                'orderable' => true,
                'render' => function ($value, TeamMember $member) {
                    $user = $member->getUser();
                    $isOwner = $member->getTeam()->getOwner() === $user;
                    
                    return sprintf(
                        '<div class="d-flex align-items-center">
                            <div class="avatar-sm bg-%s rounded-circle d-flex align-items-center justify-content-center me-2">
                                <i class="fas fa-%s text-white"></i>
                            </div>
                            <div>
                                <div class="fw-bold">%s %s</div>
                                <small class="text-muted">%s</small>
                            </div>
                        </div>',
                        $isOwner ? 'primary' : 'secondary',
                        $isOwner ? 'crown' : 'user',
                        htmlspecialchars($user->getFirstName()),
                        htmlspecialchars($user->getLastName()),
                        htmlspecialchars($user->getEmail())
                    );
                }
            ])
            ->add('role', TwigColumn::class, [
                'label' => 'Rôle',
                'template' => 'admin/datatable/team_member/role.html.twig',
                'searchable' => true,
                'orderable' => true,
            ])
            ->add('permissions', TwigColumn::class, [
                'label' => 'Permissions',
                'template' => 'admin/datatable/team_member/permissions.html.twig',
                'searchable' => false,
                'orderable' => false,
            ])
            ->add('status', TwigColumn::class, [
                'label' => 'Statut',
                'template' => 'admin/datatable/team_member/status.html.twig',
                'searchable' => false,
                'orderable' => false,
            ])
            ->add('lastActivity', DateTimeColumn::class, [
                'label' => 'Dernière activité',
                'field' => 'tm.lastActivityAt',
                'format' => 'd/m/Y H:i',
                'searchable' => false,
                'orderable' => true,
                'render' => function ($value, TeamMember $member) {
                    $lastActivity = $member->getLastActivityAt();
                    
                    if (!$lastActivity) {
                        return '<span class="text-muted">Jamais</span>';
                    }
                    
                    $diff = $lastActivity->diff(new \DateTime());
                    $isRecent = $member->isRecentlyActive(7);
                    
                    $badgeClass = $isRecent ? 'bg-success' : 'bg-warning';
                    $text = $isRecent ? 'Récent' : 'Inactif';
                    
                    return sprintf(
                        '<div>
                            <div>%s</div>
                            <span class="badge %s">%s</span>
                        </div>',
                        $lastActivity->format('d/m/Y H:i'),
                        $badgeClass,
                        $text
                    );
                }
            ])
            ->add('joinedAt', DateTimeColumn::class, [
                'label' => 'Rejoint le',
                'field' => 'tm.joinedAt',
                'format' => 'd/m/Y',
                'searchable' => false,
                'orderable' => true,
                'render' => function ($value, TeamMember $member) {
                    $joinedAt = $member->getJoinedAt();
                    $days = $member->getDaysSinceJoined();
                    
                    return sprintf(
                        '<div>
                            <div>%s</div>
                            <small class="text-muted">Il y a %d jour%s</small>
                        </div>',
                        $joinedAt->format('d/m/Y'),
                        $days,
                        $days > 1 ? 's' : ''
                    );
                }
            ])
            ->add('invitedBy', TextColumn::class, [
                'label' => 'Invité par',
                'field' => 'invitedBy.firstName',
                'searchable' => true,
                'orderable' => true,
                'render' => function ($value, TeamMember $member) {
                    $invitedBy = $member->getInvitedBy();
                    
                    if (!$invitedBy) {
                        return '<span class="text-muted">-</span>';
                    }
                    
                    return sprintf(
                        '<div>
                            <div class="fw-bold">%s</div>
                            <small class="text-muted">%s</small>
                        </div>',
                        htmlspecialchars($invitedBy->getFullName()),
                        htmlspecialchars($invitedBy->getEmail())
                    );
                }
            ])
            ->add('actions', TwigColumn::class, [
                'label' => 'Actions',
                'template' => 'admin/datatable/team_member/actions.html.twig',
                'searchable' => false,
                'orderable' => false,
            ])
            ->createAdapter(ORMAdapter::class, [
                'entity' => TeamMember::class,
                'query' => function (QueryBuilder $builder) use ($team) {
                    $builder
                        ->select('tm', 'user', 'invitedBy', 'team')
                        ->from(TeamMember::class, 'tm')
                        ->join('tm.user', 'user')
                        ->join('tm.team', 'team')
                        ->leftJoin('tm.invitedBy', 'invitedBy');
                        
                    if ($team) {
                        $builder
                            ->where('tm.team = :team')
                            ->setParameter('team', $team);
                    }
                },
            ]);
    }
}