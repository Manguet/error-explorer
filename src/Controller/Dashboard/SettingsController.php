<?php

namespace App\Controller\Dashboard;

use App\Form\UserSettingsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/settings', name: 'dashboard_settings_')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(UserSettingsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            $this->addFlash('success', 'Vos paramètres ont été mis à jour avec succès.');
            return $this->redirectToRoute('dashboard_settings_index');
        }

        return $this->render('dashboard/settings/index.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/profile', name: 'profile')]
    public function profile(Request $request): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
            $company = $request->request->get('company');

            if ($firstName && $lastName) {
                $user->setFirstName($firstName)
                     ->setLastName($lastName)
                     ->setCompany($company)
                     ->setUpdatedAt(new \DateTime());

                $this->entityManager->flush();

                $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
                return $this->redirectToRoute('dashboard_settings_profile');
            } else {
                $this->addFlash('error', 'Le prénom et le nom sont obligatoires.');
            }
        }

        return $this->render('dashboard/settings/profile.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/notifications', name: 'notifications')]
    public function notifications(Request $request): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $emailAlertsEnabled = $request->request->getBoolean('emailAlertsEnabled');
            $criticalAlertsEnabled = $request->request->getBoolean('criticalAlertsEnabled');
            $weeklyReportsEnabled = $request->request->getBoolean('weeklyReportsEnabled');

            $user->setEmailAlertsEnabled($emailAlertsEnabled)
                 ->setCriticalAlertsEnabled($criticalAlertsEnabled)
                 ->setWeeklyReportsEnabled($weeklyReportsEnabled)
                 ->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            $this->addFlash('success', 'Vos préférences de notifications ont été mises à jour.');
            return $this->redirectToRoute('dashboard_settings_notifications');
        }

        return $this->render('dashboard/settings/notifications.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/account', name: 'account')]
    public function account(): Response
    {
        $user = $this->getUser();

        return $this->render('dashboard/settings/account.html.twig', [
            'user' => $user
        ]);
    }
}
