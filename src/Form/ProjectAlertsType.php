<?php

namespace App\Form;

use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectAlertsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Configuration générale des alertes
            ->add('alertsEnabled', CheckboxType::class, [
                'label' => 'Activer les alertes email',
                'help' => 'Recevoir des notifications par email quand de nouvelles erreurs sont détectées',
                'required' => false,
            ])
            
            ->add('alertsEmail', EmailType::class, [
                'label' => 'Adresse email pour les alertes',
                'help' => 'Si vide, l\'email du compte sera utilisé',
                'required' => false,
                'attr' => [
                    'placeholder' => 'votre-email@exemple.com'
                ]
            ])

            // Configuration des seuils
            ->add('alertThreshold', IntegerType::class, [
                'label' => 'Seuil d\'alerte',
                'help' => 'Nombre d\'occurrences minimum pour déclencher une alerte',
                'required' => false,
                'attr' => [
                    'min' => 1,
                    'max' => 100,
                    'placeholder' => '1'
                ]
            ])

            ->add('alertCooldownMinutes', IntegerType::class, [
                'label' => 'Délai entre alertes (minutes)',
                'help' => 'Temps d\'attente minimum entre deux alertes pour la même erreur',
                'required' => false,
                'attr' => [
                    'min' => 5,
                    'max' => 1440,
                    'placeholder' => '30'
                ]
            ])

            // Filtres d'alertes
            ->add('alertStatusFilters', ChoiceType::class, [
                'label' => 'Types d\'erreurs à signaler',
                'choices' => [
                    'Erreurs ouvertes' => 'open',
                    'Erreurs résolues' => 'resolved',
                    'Erreurs ignorées' => 'ignored',
                ],
                'multiple' => true,
                'expanded' => true,
                'help' => 'Choisissez les types d\'erreurs pour lesquels vous souhaitez recevoir des alertes',
                'required' => false,
            ])

            ->add('alertEnvironmentFilters', ChoiceType::class, [
                'label' => 'Environnements à surveiller',
                'choices' => [
                    'Production' => 'prod',
                    'Staging' => 'staging',
                    'Development' => 'dev',
                    'Test' => 'test',
                ],
                'multiple' => true,
                'expanded' => true,
                'help' => 'Si aucun environnement n\'est sélectionné, tous seront surveillés',
                'required' => false,
            ])

            // Configuration Slack
            ->add('slackAlertsEnabled', CheckboxType::class, [
                'label' => 'Activer les alertes Slack',
                'help' => 'Envoyer les notifications vers un canal Slack',
                'required' => false,
            ])

            ->add('slackWebhookUrl', UrlType::class, [
                'label' => 'URL Webhook Slack',
                'help' => 'URL du webhook Slack pour ce projet (optionnel si configuré globalement)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK'
                ]
            ])

            ->add('slackChannel', TextType::class, [
                'label' => 'Canal Slack',
                'help' => 'Canal Slack pour ce projet (ex: #errors-staging)',
                'required' => false,
                'attr' => [
                    'placeholder' => '#errors'
                ]
            ])

            ->add('slackUsername', TextType::class, [
                'label' => 'Nom d\'utilisateur Slack',
                'help' => 'Nom qui apparaîtra comme expéditeur des messages',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Error Explorer'
                ]
            ])

            // Configuration Discord
            ->add('discordAlertsEnabled', CheckboxType::class, [
                'label' => 'Activer les alertes Discord',
                'help' => 'Envoyer les notifications vers un canal Discord',
                'required' => false,
            ])

            ->add('discordWebhookUrl', UrlType::class, [
                'label' => 'URL Webhook Discord',
                'help' => 'URL du webhook Discord pour ce projet (optionnel si configuré globalement)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://discord.com/api/webhooks/YOUR/DISCORD/WEBHOOK'
                ]
            ])

            ->add('discordUsername', TextType::class, [
                'label' => 'Nom d\'utilisateur Discord',
                'help' => 'Nom qui apparaîtra comme expéditeur des messages',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Error Explorer'
                ]
            ])

            // Résumés quotidiens
            ->add('dailySummaryEnabled', CheckboxType::class, [
                'label' => 'Activer le résumé quotidien',
                'help' => 'Recevoir un email quotidien avec un résumé des erreurs du projet',
                'required' => false,
            ])

            // Configuration des webhooks externes
            ->add('externalWebhooksEnabled', CheckboxType::class, [
                'label' => 'Activer les webhooks externes',
                'help' => 'Envoyer les données d\'erreur vers vos systèmes externes (API, automatisation, etc.)',
                'required' => false,
            ])

            ->add('submit', SubmitType::class, [
                'label' => 'Sauvegarder la configuration',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}