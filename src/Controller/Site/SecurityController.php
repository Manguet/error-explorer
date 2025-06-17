<?php

namespace App\Controller\Site;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    #[Route('/security', name: 'security')]
    public function index(): Response
    {
        $securityData = [
            'security_measures' => [
                [
                    'icon' => 'shield-check',
                    'title' => 'Chiffrement End-to-End',
                    'description' => 'Toutes vos données sont chiffrées'
                ],
                [
                    'icon' => 'key',
                    'title' => 'Authentification Multi-Facteurs',
                    'description' => 'Sécurisez votre compte avec la double authentification'
                ],
                [
                    'icon' => 'eye-off',
                    'title' => 'Anonymisation des Données',
                    'description' => 'Les données sensibles sont automatiquement anonymisées'
                ],
                [
                    'icon' => 'server',
                    'title' => 'Infrastructure Sécurisée',
                    'description' => 'Hébergement sur des serveurs sécurisés avec surveillance 24/7'
                ],
                [
                    'icon' => 'users',
                    'title' => 'Contrôle d\'Accès',
                    'description' => 'Gestion granulaire des permissions et des rôles utilisateurs'
                ],
                [
                    'icon' => 'activity',
                    'title' => 'Audit et Monitoring',
                    'description' => 'Surveillance continue et journalisation de toutes les activités'
                ]
            ],
            'privacy_features' => [
                [
                    'title' => 'Conservation des Données',
                    'description' => 'Vos données sont conservées uniquement le temps nécessaire selon vos paramètres'
                ],
                [
                    'title' => 'Droit à l\'Oubli',
                    'description' => 'Suppression complète de vos données sur simple demande'
                ],
                [
                    'title' => 'Portabilité',
                    'description' => 'Exportez vos données dans un format standard à tout moment'
                ],
                [
                    'title' => 'Transparence',
                    'description' => 'Accès complet à vos données et leur utilisation'
                ]
            ],
        ];

        return $this->render('home/security.html.twig', [
            'security_data' => $securityData
        ]);
    }
}
