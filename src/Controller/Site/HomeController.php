<?php

namespace App\Controller\Site;

use App\Entity\Plan;
use App\Form\ContactType;
use App\Repository\ErrorGroupRepository;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly PlanRepository $planRepository,
        private readonly UserRepository $userRepository,
        private readonly ErrorGroupRepository $errorGroupRepository
    ) {}

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_index');
        }

        $plans = $this->planRepository->findActivePlans();

        $currentStats  = [
            'total_users' => $this->userRepository->countActiveUsers(),
            'total_errors_tracked' => $this->errorGroupRepository->count(),
            'total_projects' => $this->userRepository->createQueryBuilder('u')
                ->select('SUM(u.currentProjectsCount)')
                ->getQuery()
                ->getSingleScalarResult() ?: 0
        ];

        $trends = $this->calculateTrends();

        $stats = array_merge($currentStats, [
            'trends' => $trends
        ]);

        return $this->render('home/index.html.twig', [
            'plans' => $plans,
            'stats' => $stats
        ]);
    }

    private function calculateTrends(): array
    {
        $now = new \DateTime();
        $lastMonth = (clone $now)->modify('-1 month');
        $twoMonthsAgo = (clone $now)->modify('-2 months');

        // Tendance utilisateurs actifs
        $currentMonthUsers = $this->userRepository->countUsersThisMonth();
        $lastMonthUsers = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt < :end')
            ->setParameter('start', $twoMonthsAgo)
            ->setParameter('end', $lastMonth)
            ->getQuery()
            ->getSingleScalarResult();

        $usersTrend = $this->calculatePercentageChange($lastMonthUsers, $currentMonthUsers);

        // Tendance erreurs détectées
        $currentMonthErrors = $this->errorGroupRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.lastSeen >= :start')
            ->setParameter('start', (clone $now)->modify('first day of this month'))
            ->getQuery()
            ->getSingleScalarResult();

        $lastMonthErrors = $this->errorGroupRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.lastSeen >= :start')
            ->andWhere('e.lastSeen < :end')
            ->setParameter('start', (clone $lastMonth)->modify('first day of this month'))
            ->setParameter('end', $now->modify('first day of this month'))
            ->getQuery()
            ->getSingleScalarResult();

        $errorsTrend = $this->calculatePercentageChange($lastMonthErrors, $currentMonthErrors);

        $projectsTrend = $usersTrend;

        return [
            'users' => [
                'percentage' => abs($usersTrend),
                'direction' => $usersTrend > 5 ? 'up' : ($usersTrend < -5 ? 'down' : 'stable'),
                'class' => $usersTrend > 0 ? 'trend-up' : ($usersTrend < -5 ? 'trend-down' : 'trend-stable')
            ],
            'errors' => [
                'percentage' => abs($errorsTrend),
                'direction' => $errorsTrend > 5 ? 'up' : ($errorsTrend < -5 ? 'down' : 'stable'),
                'class' => $errorsTrend > 0 ? 'trend-up' : ($errorsTrend < -5 ? 'trend-down' : 'trend-stable')
            ],
            'projects' => [
                'percentage' => abs($projectsTrend),
                'direction' => $projectsTrend > 5 ? 'up' : ($projectsTrend < -5 ? 'down' : 'stable'),
                'class' => $projectsTrend > 0 ? 'trend-up' : ($projectsTrend < -5 ? 'trend-down' : 'trend-stable')
            ],
            'uptime' => [
                'percentage' => 0,
                'direction' => 'stable',
                'class' => 'trend-stable'
            ],
            'response_time' => [
                'percentage' => 15, // Amélioration fictive
                'direction' => 'down', // down = amélioration pour le temps de réponse
                'class' => 'trend-down'
            ],
            'resolution_time' => [
                'percentage' => 28, // Amélioration fictive
                'direction' => 'up', // up = amélioration du temps de résolution
                'class' => 'trend-up'
            ]
        ];
    }

    private function calculatePercentageChange(int $oldValue, int $newValue): float
    {
        if ($oldValue === 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 1);
    }

    #[Route('/pricing', name: 'pricing')]
    public function pricing(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_index');
        }

        $plans = $this->planRepository->findActivePlans();

        return $this->render('home/pricing.html.twig', [
            'plans' => $plans,
            'feature_categories' => Plan::getFeatureCategories()
        ]);
    }

    #[Route('/features', name: 'features')]
    public function features(): Response
    {
        return $this->render('home/features.html.twig', [
            'total_errors_tracked' => $this->errorGroupRepository->count(),
        ]);
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('home/about.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Vérification honeypot
            if (!empty($data['website'])) {
                // Bot détecté, on fait semblant que tout va bien
                $this->addFlash('success', 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.');
                return $this->redirectToRoute('contact');
            }

            try {
                // Créer l'email
                $email = (new Email())
                    ->from('error.explorer.contact@gmail.com')
                    ->to('error.explorer.contact@gmail.com')
                    ->replyTo($data['email'])
                    ->subject('[Contact] ' . $data['subject'])
                    ->html($this->renderView('emails/contact.html.twig', [
                        'data' => $data,
                        'timestamp' => new \DateTime(),
                        'ip' => $request->getClientIp(),
                        'userAgent' => $request->headers->get('User-Agent')
                    ]));

                // Envoyer l'email
                $mailer->send($email);

                // Email de confirmation au visiteur
                $confirmationEmail = (new Email())
                    ->from('noreply@errorexplorer.com')
                    ->to($data['email'])
                    ->subject('Confirmation de réception - Error Explorer')
                    ->html($this->renderView('emails/contact_confirmation.html.twig', [
                        'name' => $data['name'],
                        'subject' => $data['subject']
                    ]));

                $mailer->send($confirmationEmail);

                $this->addFlash('success', 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.');

                return $this->redirectToRoute('contact');

            } catch (\Exception) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer.');
            }
        }

        return $this->render('home/contact.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/integration', name: 'integration')]
    public function integration(): Response
    {
        return $this->render('home/integration.html.twig');
    }

    #[Route('/cgu', name: 'cgu')]
    public function cgu(): Response
    {
        return $this->render('legal/terms.html.twig');
    }

    #[Route('/cgv', name: 'cgv')]
    public function cgv(): Response
    {
        return $this->render('legal/sell.html.twig');
    }

    #[Route('/privacy', name: 'privacy')]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    #[Route('/demo', name: 'demo')]
    public function demo(): Response
    {
        // Demo avec données fictives
        return $this->render('home/demo.html.twig');
    }

    #[Route('/test-error-sdk', name: 'test_error_sdk', methods: ['GET'])]
    public function testErrorSDK(): Response
    {
        return new Response('
        <html>
        <head><title>Test Error SDK</title></head>
        <body>
            <h1>Test du SDK Error Reporter</h1>
            <p>Configurez votre SDK de test avec :</p>
            <pre>
error_reporter:
    webhook_url: "http://error-explorer.localhost"
    token: "votre-token-projet"
    project_name: "nom-du-projet"
    enabled: true
            </pre>
            <p>Puis créez une erreur dans votre projet de test pour voir si elle arrive ici.</p>
            <p>Le webhook utilise maintenant l\'endpoint de production : <code>/webhook/error/{token}</code></p>
            <p>Consultez les logs avec : <code>tail -f var/log/dev.log</code></p>
        </body>
        </html>
        ');
    }
}
