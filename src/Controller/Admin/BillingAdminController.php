<?php

namespace App\Controller\Admin;

use App\Entity\Subscription;
use App\Entity\Invoice;
use App\Repository\SubscriptionRepository;
use App\Repository\InvoiceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/billing', name: 'admin_billing_')]
#[IsGranted('ROLE_ADMIN')]
class BillingAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubscriptionRepository $subscriptionRepository,
        private InvoiceRepository $invoiceRepository,
        private UserRepository $userRepository
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        // Statistiques générales
        $stats = $this->getBillingStats();
        
        // Abonnements récents
        $recentSubscriptions = $this->subscriptionRepository->findBy(
            [], 
            ['createdAt' => 'DESC'], 
            10
        );
        
        // Factures récentes
        $recentInvoices = $this->invoiceRepository->findBy(
            [], 
            ['createdAt' => 'DESC'], 
            10
        );

        return $this->render('admin/billing/index.html.twig', [
            'stats' => $stats,
            'recent_subscriptions' => $recentSubscriptions,
            'recent_invoices' => $recentInvoices
        ]);
    }

    #[Route('/subscriptions', name: 'subscriptions')]
    public function subscriptions(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $status = $request->query->get('status');
        
        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }

        $subscriptions = $this->subscriptionRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        $total = $this->subscriptionRepository->count($criteria);
        $maxPage = ceil($total / $limit);

        return $this->render('admin/billing/subscriptions.html.twig', [
            'subscriptions' => $subscriptions,
            'current_page' => $page,
            'max_page' => $maxPage,
            'total' => $total,
            'status_filter' => $status
        ]);
    }

    #[Route('/invoices', name: 'invoices')]
    public function invoices(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $status = $request->query->get('status');
        
        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }

        $invoices = $this->invoiceRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        $total = $this->invoiceRepository->count($criteria);
        $maxPage = ceil($total / $limit);

        return $this->render('admin/billing/invoices.html.twig', [
            'invoices' => $invoices,
            'current_page' => $page,
            'max_page' => $maxPage,
            'total' => $total,
            'status_filter' => $status
        ]);
    }

    #[Route('/revenue', name: 'revenue')]
    public function revenue(Request $request): Response
    {
        $period = $request->query->get('period', 'month');
        $year = $request->query->getInt('year', date('Y'));
        $month = $request->query->getInt('month', date('n'));

        $revenueData = $this->getRevenueData($period, $year, $month);

        return $this->render('admin/billing/revenue.html.twig', [
            'revenue_data' => $revenueData,
            'period' => $period,
            'year' => $year,
            'month' => $month
        ]);
    }

    #[Route('/subscription/{id}', name: 'subscription_detail')]
    public function subscriptionDetail(int $id): Response
    {
        $subscription = $this->subscriptionRepository->find($id);
        
        if (!$subscription) {
            throw $this->createNotFoundException('Abonnement introuvable');
        }

        $invoices = $this->invoiceRepository->findBy(
            ['subscription' => $subscription],
            ['createdAt' => 'DESC']
        );

        return $this->render('admin/billing/subscription_detail.html.twig', [
            'subscription' => $subscription,
            'invoices' => $invoices
        ]);
    }

    private function getBillingStats(): array
    {
        // Nombre total d'abonnements par statut
        $subscriptionStats = $this->entityManager->createQueryBuilder()
            ->select('s.status, COUNT(s.id) as count')
            ->from(Subscription::class, 's')
            ->groupBy('s.status')
            ->getQuery()
            ->getResult();

        // Revenue total du mois en cours
        $currentMonth = new \DateTime('first day of this month');
        $nextMonth = new \DateTime('first day of next month');
        
        $monthlyRevenue = $this->entityManager->createQueryBuilder()
            ->select('SUM(i.total) as total')
            ->from(Invoice::class, 'i')
            ->where('i.status = :paid')
            ->andWhere('i.paidAt >= :start')
            ->andWhere('i.paidAt < :end')
            ->setParameter('paid', Invoice::STATUS_PAID)
            ->setParameter('start', $currentMonth)
            ->setParameter('end', $nextMonth)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Revenue total de l'année
        $yearStart = new \DateTime('first day of January this year');
        $yearEnd = new \DateTime('first day of January next year');
        
        $yearlyRevenue = $this->entityManager->createQueryBuilder()
            ->select('SUM(i.total) as total')
            ->from(Invoice::class, 'i')
            ->where('i.status = :paid')
            ->andWhere('i.paidAt >= :start')
            ->andWhere('i.paidAt < :end')
            ->setParameter('paid', Invoice::STATUS_PAID)
            ->setParameter('start', $yearStart)
            ->setParameter('end', $yearEnd)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Nombre de nouveaux abonnements ce mois
        $newSubscriptions = $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Subscription::class, 's')
            ->where('s.createdAt >= :start')
            ->andWhere('s.createdAt < :end')
            ->setParameter('start', $currentMonth)
            ->setParameter('end', $nextMonth)
            ->getQuery()
            ->getSingleScalarResult();

        // Churn rate (annulations ce mois)
        $cancelledSubscriptions = $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Subscription::class, 's')
            ->where('s.canceledAt >= :start')
            ->andWhere('s.canceledAt < :end')
            ->setParameter('start', $currentMonth)
            ->setParameter('end', $nextMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'subscription_stats' => $subscriptionStats,
            'monthly_revenue' => (float) $monthlyRevenue,
            'yearly_revenue' => (float) $yearlyRevenue,
            'new_subscriptions' => (int) $newSubscriptions,
            'cancelled_subscriptions' => (int) $cancelledSubscriptions,
            'total_active_subscriptions' => $this->subscriptionRepository->count(['status' => Subscription::STATUS_ACTIVE])
        ];
    }

    private function getRevenueData(string $period, int $year, int $month): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('i.paidAt, SUM(i.total) as revenue, COUNT(i.id) as invoice_count')
            ->from(Invoice::class, 'i')
            ->where('i.status = :paid')
            ->setParameter('paid', Invoice::STATUS_PAID);

        if ($period === 'month') {
            $start = new \DateTime("$year-$month-01");
            $end = clone $start;
            $end->modify('last day of this month')->setTime(23, 59, 59);
            
            $qb->andWhere('i.paidAt >= :start')
               ->andWhere('i.paidAt <= :end')
               ->setParameter('start', $start)
               ->setParameter('end', $end)
               ->addSelect('DAY(i.paidAt) as period_label')
               ->groupBy('DAY(i.paidAt)')
               ->orderBy('DAY(i.paidAt)', 'ASC');
        } else {
            $start = new \DateTime("$year-01-01");
            $end = new \DateTime("$year-12-31 23:59:59");
            
            $qb->andWhere('i.paidAt >= :start')
               ->andWhere('i.paidAt <= :end')
               ->setParameter('start', $start)
               ->setParameter('end', $end)
               ->addSelect('MONTH(i.paidAt) as period_label')
               ->groupBy('MONTH(i.paidAt)')
               ->orderBy('MONTH(i.paidAt)', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}