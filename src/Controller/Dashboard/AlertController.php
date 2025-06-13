<?php

namespace App\Controller\Dashboard;

use App\Repository\ErrorGroupRepository;
use App\Repository\ProjectRepository;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/alerts')]
#[IsGranted('ROLE_USER')]
class AlertController extends AbstractController
{
    public function __construct(
        private readonly ErrorGroupRepository $errorGroupRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger
    ) {}

    #[Route('', name: 'alerts_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $projects = $this->projectRepository->findByOwner($user);

        // Récupération des logs d'alertes récents
        $recentAlerts = $this->getRecentAlerts($user, 50);

        // Statistiques des alertes
        $alertStats = $this->getAlertStatistics($user);

        // Configuration globale des alertes
        $globalSettings = [
            'email_alerts_enabled' => $this->settingsManager->getSetting('email.error_alerts', false),
            'smtp_configured' => $this->settingsManager->getSetting('email.smtp_host') !== null,
        ];

        return $this->render('dashboard/alerts/index.html.twig', [
            'projects' => $projects,
            'recent_alerts' => $recentAlerts,
            'alert_stats' => $alertStats,
            'global_settings' => $globalSettings,
            'user_settings' => [
                'email_alerts_enabled' => $user->isEmailAlertsEnabled(),
                'critical_alerts_enabled' => $user->isCriticalAlertsEnabled(),
            ]
        ]);
    }

    #[Route('/logs', name: 'alerts_logs', methods: ['GET'])]
    public function logs(Request $request): Response
    {
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Filtres
        $projectId = $request->query->get('project');
        $status = $request->query->get('status');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        $alerts = $this->getAlertLogs($user, $limit, $offset, [
            'project_id' => $projectId,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $totalAlerts = $this->countAlertLogs($user, [
            'project_id' => $projectId,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $totalPages = ceil($totalAlerts / $limit);

        return $this->render('dashboard/alerts/logs.html.twig', [
            'alerts' => $alerts,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_alerts' => $totalAlerts,
            'projects' => $this->projectRepository->findByOwner($user),
            'filters' => [
                'project' => $projectId,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]
        ]);
    }

    #[Route('/settings', name: 'alerts_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Mise à jour des préférences utilisateur
            if (isset($data['email_alerts_enabled'])) {
                $user->setEmailAlertsEnabled((bool)$data['email_alerts_enabled']);
            }

            if (isset($data['critical_alerts_enabled'])) {
                $user->setCriticalAlertsEnabled((bool)$data['critical_alerts_enabled']);
            }

            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('success', 'Paramètres des alertes mis à jour avec succès.');
            return $this->redirectToRoute('alerts_settings');
        }

        return $this->render('dashboard/alerts/settings.html.twig', [
            'user' => $user,
            'global_settings' => [
                'email_alerts_enabled' => $this->settingsManager->getSetting('email.error_alerts', false),
                'smtp_configured' => $this->settingsManager->getSetting('email.smtp_host', null) !== null,
            ]
        ]);
    }

    #[Route('/test', name: 'alerts_test', methods: ['POST'])]
    public function testAlert(Request $request, MailerInterface $mailer): JsonResponse
    {
        $user = $this->getUser();

        // Vérifier si les alertes sont activées globalement
        if (!$this->settingsManager->getSetting('email.error_alerts', false)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Les alertes par email sont désactivées par l\'administrateur.'
            ], 400);
        }

        if (!$user->isEmailAlertsEnabled()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Vous avez désactivé les alertes par email dans vos paramètres.'
            ], 400);
        }

        try {
            // Créer un email de test
            $email = (new Email())
                ->from('alerts@errorexplorer.com')
                ->to($user->getEmail())
                ->subject('[Test] Alerte Error Explorer')
                ->html($this->renderView('emails/test_alert.html.twig', [
                    'user' => $user
                ]));

            $mailer->send($email);

            $this->logger->info('Test alert sent successfully', [
                'user_email' => $user->getEmail()
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Email de test envoyé avec succès à ' . $user->getEmail()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send test alert', [
                'error' => $e->getMessage(),
                'user_email' => $user->getEmail()
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email de test : ' . $e->getMessage()
            ], 500);
        }
    }

    private function getRecentAlerts($user, int $limit = 10): array
    {
        // Récupération des erreurs récentes avec alertes envoyées
        return $this->errorGroupRepository->createQueryBuilder('eg')
            ->innerJoin('eg.projectEntity', 'p')
            ->where('p.owner = :user')
            ->andWhere('eg.lastAlertSentAt IS NOT NULL')
            ->orderBy('eg.lastAlertSentAt', 'DESC')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function getAlertStatistics($user): array
    {
        $qb = $this->errorGroupRepository->createQueryBuilder('eg')
            ->innerJoin('eg.projectEntity', 'p')
            ->where('p.owner = :user')
            ->setParameter('user', $user);

        // Total des alertes envoyées
        $totalAlerts = (clone $qb)
            ->select('COUNT(eg.id)')
            ->andWhere('eg.lastAlertSentAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        // Alertes des 24 dernières heures
        $todayAlerts = (clone $qb)
            ->select('COUNT(eg.id)')
            ->andWhere('eg.lastAlertSentAt >= :today')
            ->andWhere('eg.lastAlertSentAt IS NOT NULL')
            ->setParameter('today', new \DateTime('-24 hours'))
            ->getQuery()
            ->getSingleScalarResult();

        // Alertes de la semaine
        $weekAlerts = (clone $qb)
            ->select('COUNT(eg.id)')
            ->andWhere('eg.lastAlertSentAt >= :week')
            ->andWhere('eg.lastAlertSentAt IS NOT NULL')
            ->setParameter('week', new \DateTime('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        // Projets avec alertes actives
        $activeProjects = (clone $qb)
            ->select('COUNT(DISTINCT p.id)')
            ->innerJoin('eg.projectEntity', 'p2')
            ->andWhere('p2.alertsEnabled = true')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_alerts' => $totalAlerts,
            'today_alerts' => $todayAlerts,
            'week_alerts' => $weekAlerts,
            'active_projects' => $activeProjects,
        ];
    }

    private function getAlertLogs($user, int $limit, int $offset, array $filters = []): array
    {
        $qb = $this->errorGroupRepository->createQueryBuilder('eg')
            ->innerJoin('eg.projectEntity', 'p')
            ->where('p.owner = :user')
            ->andWhere('eg.lastAlertSentAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('eg.lastAlertSentAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (!empty($filters['project_id'])) {
            $qb->andWhere('p.id = :projectId')
               ->setParameter('projectId', $filters['project_id']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('eg.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('eg.lastAlertSentAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('eg.lastAlertSentAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['date_to'] . ' 23:59:59'));
        }

        return $qb->getQuery()->getResult();
    }

    private function countAlertLogs($user, array $filters = []): int
    {
        $qb = $this->errorGroupRepository->createQueryBuilder('eg')
            ->select('COUNT(eg.id)')
            ->innerJoin('eg.projectEntity', 'p')
            ->where('p.owner = :user')
            ->andWhere('eg.lastAlertSentAt IS NOT NULL')
            ->setParameter('user', $user);

        if (!empty($filters['project_id'])) {
            $qb->andWhere('p.id = :projectId')
               ->setParameter('projectId', $filters['project_id']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('eg.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('eg.lastAlertSentAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('eg.lastAlertSentAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['date_to'] . ' 23:59:59'));
        }

        return $qb->getQuery()->getSingleScalarResult();
    }
}
