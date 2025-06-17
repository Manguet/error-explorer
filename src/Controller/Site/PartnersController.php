<?php

namespace App\Controller\Site;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PartnersController extends AbstractController
{
    #[Route('/partenaires', name: 'partners')]
    public function index(): Response
    {
        $partners = $this->getPartnersData();

        return $this->render('home/partners.html.twig', [
            'partners' => $partners,
        ]);
    }

    private function getPartnersData(): array
    {
        return [
            'technology' => [
                [
                    'name' => 'Slack',
                    'logo' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/slack/slack-original.svg',
                    'description' => 'Intégration native pour recevoir les alertes d\'erreurs directement dans vos canaux Slack.',
                    'features' => [
                        'Notifications temps réel',
                        'Configuration par canal',
                        'Alertes personnalisables',
                        'Escalade automatique'
                    ],
                    'status' => 'active',
                    'category' => 'Communication',
                    'link' => '#'
                ],
                [
                    'name' => 'Discord',
                    'logo' => 'https://assets-global.website-files.com/6257adef93867e50d84d30e2/636e0a69f118df70ad7828d4_icon_clyde_blurple_RGB.svg',
                    'description' => 'Recevez vos alertes d\'erreurs dans vos serveurs Discord pour une collaboration optimale.',
                    'features' => [
                        'Webhooks sécurisés',
                        'Messages enrichis',
                        'Mentions automatiques',
                        'Gestion par rôles'
                    ],
                    'status' => 'active',
                    'category' => 'Communication',
                    'link' => '#'
                ],
                [
                    'name' => 'GitHub',
                    'logo' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/github/github-original.svg',
                    'description' => 'Intégration avec GitHub pour créer automatiquement des issues à partir des erreurs.',
                    'features' => [
                        'Création d\'issues automatiques',
                        'Liens vers le code source',
                        'Assignation intelligente',
                        'Labels personnalisés'
                    ],
                    'status' => 'beta',
                    'category' => 'Développement',
                    'link' => '#'
                ],
                [
                    'name' => 'GitLab',
                    'logo' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/gitlab/gitlab-original.svg',
                    'description' => 'Synchronisation avec GitLab pour un workflow de développement fluide.',
                    'features' => [
                        'Issues automatiques',
                        'Merge requests liées',
                        'Pipeline d\'intégration',
                        'Métriques de qualité'
                    ],
                    'status' => 'coming-soon',
                    'category' => 'Développement',
                    'link' => '#'
                ]
            ],
            'monitoring' => [
                [
                    'name' => 'Datadog',
                    'logo' => 'https://imgix.datadoghq.com/img/about/presskit/logo-v/dd_vertical_purple.png',
                    'description' => 'Enrichissez vos métriques Datadog avec les données d\'erreurs d\'Error Explorer.',
                    'features' => [
                        'Métriques personnalisées',
                        'Dashboards intégrés',
                        'Alertes avancées',
                        'Correlation des événements'
                    ],
                    'status' => 'coming-soon',
                    'category' => 'Monitoring',
                    'link' => '#'
                ],
                [
                    'name' => 'New Relic',
                    'logo' => 'https://newrelic.com/themes/custom/erno/assets/mediakit/new_relic_logo_horizontal.svg',
                    'description' => 'Corrélation des erreurs avec les performances applicatives New Relic.',
                    'features' => [
                        'APM intégré',
                        'Traces distribuées',
                        'Alertes intelligentes',
                        'Analyse de performance'
                    ],
                    'status' => 'coming-soon',
                    'category' => 'Monitoring',
                    'link' => '#'
                ],
                [
                    'name' => 'Grafana',
                    'logo' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/grafana/grafana-original.svg',
                    'description' => 'Visualisez vos erreurs dans vos dashboards Grafana existants.',
                    'features' => [
                        'Dashboards personnalisés',
                        'Métriques temps réel',
                        'Alertes configurables',
                        'Panels dédiés'
                    ],
                    'status' => 'coming-soon',
                    'category' => 'Monitoring',
                    'link' => '#'
                ]
            ],
            'cloud' => [
                [
                    'name' => 'AWS',
                    'logo' => 'https://www.svgrepo.com/show/448266/aws.svg',
                    'description' => 'Intégration native avec les services AWS pour un monitoring cloud complet.',
                    'features' => [
                        'CloudWatch intégration',
                        'Lambda triggers',
                        'S3 storage',
                        'SNS notifications'
                    ],
                    'status' => 'coming-soon',
                    'category' => 'Cloud',
                    'link' => '#'
                ],
                [
                    'name' => 'Google Cloud',
                    'logo' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/googlecloud/googlecloud-original.svg',
                    'description' => 'Surveillance des applications déployées sur Google Cloud Platform.',
                    'features' => [
                        'Cloud Logging',
                        'Error Reporting',
                        'Cloud Functions',
                        'Pub/Sub messaging'
                    ],
                    'status' => 'coming-soon',
                    'category' => 'Cloud',
                    'link' => '#'
                ],
                [
                    'name' => 'Microsoft Azure',
                    'logo' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/azure/azure-original.svg',
                    'description' => 'Intégration avec l\'écosystème Microsoft Azure pour vos applications.',
                    'features' => [
                        'Application Insights',
                        'Azure Functions',
                        'Service Bus',
                        'Logic Apps'
                    ],
                    'status' => 'coming-soon',
                    'category' => 'Cloud',
                    'link' => '#'
                ]
            ]
        ];
    }
}
