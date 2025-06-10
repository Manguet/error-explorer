<?php

namespace App\Form;

use App\Entity\Plan;
use App\Entity\User;
use App\Repository\PlanRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];
        $isAdmin = $options['is_admin'];

        $builder
            // Informations personnelles
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'John',
                    'class' => 'form-input',
                    'maxlength' => 100
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prénom est requis'),
                    new Assert\Length(max: 100, maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères')
                ]
            ])

            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Doe',
                    'class' => 'form-input',
                    'maxlength' => 100
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est requis'),
                    new Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')
                ]
            ])

            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'placeholder' => 'john.doe@exemple.com',
                    'class' => 'form-input',
                    'maxlength' => 180
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'email est requis'),
                    new Assert\Email(message: 'Veuillez saisir une adresse email valide'),
                    new Assert\Length(max: 180, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères')
                ]
            ])

            ->add('company', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom de l\'entreprise (optionnel)',
                    'class' => 'form-input',
                    'maxlength' => 100
                ],
                'constraints' => [
                    new Assert\Length(max: 100, maxMessage: 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères')
                ]
            ]);

        // Mot de passe seulement pour création ou changement
        if (!$isEdit) {
            $builder->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Mot de passe sécurisé',
                    'class' => 'form-input',
                    'autocomplete' => 'new-password'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mot de passe est requis'),
                    new Assert\Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères'
                    ),
                    new Assert\Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                        message: 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre'
                    )
                ]
            ]);
        } else {
            $builder->add('newPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Laissez vide pour ne pas changer',
                    'class' => 'form-input',
                    'autocomplete' => 'new-password'
                ],
                'help' => 'Laissez vide si vous ne souhaitez pas changer le mot de passe',
                'constraints' => [
                    new Assert\Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères'
                    ),
                    new Assert\Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                        message: 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre'
                    )
                ]
            ]);
        }

        // Plan (liste déroulante avec informations détaillées)
        $builder->add('plan', EntityType::class, [
            'class' => Plan::class,
            'choice_label' => function (Plan $plan) {
                $priceLabel = $plan->isFree() ? 'Gratuit' : $plan->getFormattedPriceMonthly() . '/mois';
                return sprintf('%s (%s - %s projets, %s erreurs/mois)',
                    $plan->getName(),
                    $priceLabel,
                    $plan->getMaxProjectsLabel(),
                    $plan->getMaxMonthlyErrorsLabel()
                );
            },
            'query_builder' => function (PlanRepository $repo) {
                return $repo->createQueryBuilder('p')
                    ->where('p.isActive = true')
                    ->orderBy('p.sortOrder', 'ASC')
                    ->addOrderBy('p.priceMonthly', 'ASC');
            },
            'placeholder' => 'Sélectionner un plan',
            'label' => 'Plan d\'abonnement',
            'attr' => [
                'class' => 'form-select',
                'onchange' => 'updatePlanInfo()'
            ],
            'constraints' => [
                new Assert\NotBlank(message: 'Veuillez sélectionner un plan')
            ]
        ]);

        // Date d'expiration du plan
        $builder->add('planExpiresAt', DateTimeType::class, [
            'label' => 'Expiration du plan',
            'required' => false,
            'widget' => 'single_text',
            'attr' => [
                'class' => 'form-input',
                'min' => (new \DateTime())->format('Y-m-d\TH:i')
            ],
            'help' => 'Laissez vide pour un plan permanent'
        ]);

        // Options admin uniquement
        if ($isAdmin) {
            $builder
                ->add('roles', ChoiceType::class, [
                    'label' => 'Rôles',
                    'choices' => [
                        'Utilisateur' => 'ROLE_USER',
                        'Administrateur' => 'ROLE_ADMIN',
                        'Super Admin' => 'ROLE_SUPER_ADMIN'
                    ],
                    'multiple' => true,
                    'expanded' => true,
                    'attr' => [
                        'class' => 'form-checkbox-group',
                    ],
                    'help' => 'Sélectionnez les rôles de l\'utilisateur'
                ])

                ->add('isActive', CheckboxType::class, [
                    'label' => 'Compte actif',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-checkbox'
                    ],
                    'help' => 'Un compte inactif ne peut pas se connecter'
                ])

                ->add('isVerified', CheckboxType::class, [
                    'label' => 'Email vérifié',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-checkbox'
                    ],
                    'help' => 'Marquer l\'email comme vérifié'
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'is_admin' => true,
            'attr' => ['class' => 'user-form', 'novalidate' => 'novalidate']
        ]);
    }
}
