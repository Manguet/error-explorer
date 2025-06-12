<?php

namespace App\Form;

use App\Entity\Plan;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Informations de base
            ->add('name', TextType::class, [
                'label' => 'Nom du plan',
                'attr' => [
                    'placeholder' => 'Ex: Pro, Enterprise...',
                    'maxlength' => 100,
                    'onkeyup' => 'updateSlugPreview()',
                    'class' => 'form-input'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom du plan est requis'),
                    new Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')
                ]
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'maxlength' => 500,
                    'placeholder' => 'Description courte du plan...',
                    'class' => 'form-textarea'
                ],
                'constraints' => [
                    new Assert\Length(max: 500, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')
                ]
            ])

            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => [
                    'min' => 0,
                    'max' => 999,
                    'class' => 'form-input'
                ],
                'data' => 0,
                'help' => 'Plus le nombre est petit, plus le plan apparaît en premier'
            ])

            // Tarification
            ->add('priceMonthly', NumberType::class, [
                'label' => 'Prix mensuel (€)',
                'scale' => 2,
                'attr' => [
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => '0.00',
                    'onkeyup' => 'updatePreview()',
                    'class' => 'form-input'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prix mensuel est requis'),
                    new Assert\PositiveOrZero(message: 'Le prix mensuel doit être positif ou nul')
                ]
            ])

            ->add('priceYearly', NumberType::class, [
                'label' => 'Prix annuel (€)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => '0.00 (optionnel)',
                    'onkeyup' => 'updatePreview()',
                    'class' => 'form-input'
                ],
                'help' => 'Laisser vide si pas de tarif annuel',
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'Le prix annuel doit être positif ou nul')
                ]
            ])

            // Stripe Configuration
            ->add('stripePriceIdMonthly', TextType::class, [
                'label' => 'Stripe Price ID (Mensuel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'price_1234567890abcdef',
                    'class' => 'form-input'
                ],
                'help' => 'ID du prix mensuel dans Stripe (ex: price_1234567890abcdef)',
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^price_[a-zA-Z0-9_]+$/',
                        message: 'Le Price ID doit commencer par "price_" et contenir uniquement des caractères alphanumériques et underscores'
                    )
                ]
            ])

            ->add('stripePriceIdYearly', TextType::class, [
                'label' => 'Stripe Price ID (Annuel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'price_0987654321fedcba',
                    'class' => 'form-input'
                ],
                'help' => 'ID du prix annuel dans Stripe (ex: price_0987654321fedcba)',
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^price_[a-zA-Z0-9_]+$/',
                        message: 'Le Price ID doit commencer par "price_" et contenir uniquement des caractères alphanumériques et underscores'
                    )
                ]
            ])

            ->add('trialDays', IntegerType::class, [
                'label' => 'Jours d\'essai gratuit',
                'required' => false,
                'attr' => [
                    'min' => '0',
                    'max' => '365',
                    'placeholder' => '14',
                    'class' => 'form-input'
                ],
                'help' => 'Nombre de jours d\'essai gratuit (0 = pas d\'essai)',
                'constraints' => [
                    new Assert\Range(min: 0, max: 365, notInRangeMessage: 'Les jours d\'essai doivent être entre {{ min }} et {{ max }}')
                ]
            ])

            // Limites
            ->add('maxProjects', IntegerType::class, [
                'label' => 'Projets maximum',
                'attr' => [
                    'min' => '-1',
                    'placeholder' => '1',
                    'onkeyup' => 'updatePreview()',
                    'class' => 'form-input'
                ],
                'help' => '-1 pour illimité',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nombre maximum de projets est requis'),
                    new Assert\GreaterThanOrEqual(value: -1, message: 'Le nombre de projets doit être -1 (illimité) ou positif')
                ]
            ])

            ->add('maxMonthlyErrors', IntegerType::class, [
                'label' => 'Erreurs/mois',
                'attr' => [
                    'min' => '-1',
                    'placeholder' => '500',
                    'onkeyup' => 'updatePreview()',
                    'class' => 'form-input'
                ],
                'help' => '-1 pour illimité',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nombre maximum d\'erreurs est requis'),
                    new Assert\GreaterThanOrEqual(value: -1, message: 'Le nombre d\'erreurs doit être -1 (illimité) ou positif')
                ]
            ])

            ->add('dataRetentionDays', IntegerType::class, [
                'label' => 'Rétention (jours)',
                'attr' => [
                    'min' => '1',
                    'max' => '365',
                    'onkeyup' => 'updatePreview()',
                    'class' => 'form-input'
                ],
                'data' => 30,
                'help' => 'Durée de conservation des données',
                'constraints' => [
                    new Assert\NotBlank(message: 'La durée de rétention est requise'),
                    new Assert\Range(min: 1, max: 365, notInRangeMessage: 'La rétention doit être entre {{ min }} et {{ max }} jours')
                ]
            ])

            // Fonctionnalités
            ->add('hasEmailAlerts', CheckboxType::class, [
                'label' => 'Alertes email',
                'required' => false,
                'attr' => [
                    'onchange' => 'updatePreview()',
                    'class' => 'form-checkbox'
                ]
            ])

            ->add('hasApiAccess', CheckboxType::class, [
                'label' => 'Accès API',
                'required' => false,
                'attr' => [
                    'onchange' => 'updatePreview()',
                    'class' => 'form-checkbox'
                ]
            ])

            ->add('hasSlackIntegration', CheckboxType::class, [
                'label' => 'Intégration Slack',
                'required' => false,
                'attr' => [
                    'onchange' => 'updatePreview()',
                    'class' => 'form-checkbox'
                ]
            ])

            ->add('hasAdvancedFilters', CheckboxType::class, [
                'label' => 'Filtres avancés',
                'required' => false,
                'attr' => [
                    'onchange' => 'updatePreview()',
                    'class' => 'form-checkbox'
                ]
            ])

            ->add('hasAdvancedAnalytics', CheckboxType::class, [
                'label' => 'Analytique avancée',
                'required' => false,
                'attr' => [
                    'onchange' => 'updatePreview()',
                    'class' => 'form-checkbox'
                ]
            ])

            ->add('hasPrioritySupport', CheckboxType::class, [
                'label' => 'Support prioritaire',
                'required' => false,
                'attr' => [
                    'onchange' => 'updatePreview()',
                    'class' => 'form-checkbox'
                ]
            ])

            ->add('hasCustomRetention', CheckboxType::class, [
                'label' => 'Rétention personnalisée',
                'required' => false,
                'attr' => [
                    'onchange' => 'updatePreview()',
                    'class' => 'form-checkbox'
                ]
            ])

            // Options
            ->add('isActive', CheckboxType::class, [
                'label' => 'Plan actif',
                'required' => false,
                'data' => true,
                'attr' => [
                    'onchange' => 'updatePreview()',
                    'class' => 'form-checkbox'
                ],
                'help' => 'Le plan peut être sélectionné par les utilisateurs'
            ])

            ->add('isPopular', CheckboxType::class, [
                'label' => 'Plan populaire',
                'required' => false,
                'attr' => [
                    'onchange' => 'updatePreview()',
                    'class' => 'form-checkbox'
                ],
                'help' => 'Plan mis en avant avec un badge "Populaire"'
            ])

            ->add('isBuyable', CheckboxType::class, [
                'label' => 'Plan disponible à l\'achat',
                'required' => false,
                'attr' => [
                    'class' => 'form-checkbox'
                ],
                'help' => 'Plan disponible à l\'achat pour les utilisateurs',
                'data' => $options['data']->isBuyable()
            ])

            ->add('features', TextareaType::class, [
                'label' => 'Fonctionnalités détaillées (optionnel)',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => "Une fonctionnalité par ligne\nSupport 24/7\nRapports personnalisés\nIntégrations illimitées",
                    'class' => 'form-textarea'
                ],
                'help' => 'Une fonctionnalité par ligne, sera affiché sous forme de liste à puces'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Plan::class,
            'attr' => ['class' => 'plan-form', 'id' => 'plan-form']
        ]);
    }
}
