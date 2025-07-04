<?php

namespace App\Form\Register;

use App\Entity\Plan;
use App\Entity\User;
use App\Repository\PlanRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom *',
                'attr' => [
                    'placeholder' => 'John',
                    'class' => 'form-input',
                    'autocomplete' => 'given-name',
                    'id' => 'registration_form_firstName',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le prénom est requis',
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères',
                        'max' => 50,
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
                        'message' => 'Le prénom contient des caractères non valides',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom *',
                'attr' => [
                    'placeholder' => 'Doe',
                    'class' => 'form-input',
                    'autocomplete' => 'family-name',
                    'id' => 'registration_form_lastName',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom est requis',
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'max' => 50,
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
                        'message' => 'Le nom contient des caractères non valides',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email *',
                'attr' => [
                    'placeholder' => 'john@entreprise.com',
                    'class' => 'form-input',
                    'autocomplete' => 'email',
                    'id' => 'registration_form_email',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'adresse email est requise',
                    ]),
                    new Email([
                        'message' => 'Veuillez saisir une adresse email valide',
                    ]),
                ],
            ])
            ->add('company', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom de votre entreprise',
                    'class' => 'form-input',
                    'autocomplete' => 'organization',
                    'id' => 'registration_form_company',
                ],
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Mot de passe *',
                    'attr' => [
                        'placeholder' => '••••••••••••',
                        'class' => 'form-input form-input--password',
                        'autocomplete' => 'new-password',
                        'id' => 'registration_form_plainPassword_first',
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Le mot de passe est requis',
                        ]),
                        new Length([
                            'min' => 8,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
                            'max' => 128,
                            'maxMessage' => 'Le mot de passe ne peut pas dépasser {{ limit }} caractères',
                        ]),
                        new Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                            'message' => 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre',
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe *',
                    'attr' => [
                        'placeholder' => '••••••••••••',
                        'class' => 'form-input form-input--password',
                        'autocomplete' => 'new-password',
                        'id' => 'registration_form_plainPassword_second',
                    ],
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas',
            ])
            ->add('plan', EntityType::class, [
                'class' => Plan::class,
                'choice_label' => 'name',
                'query_builder' => function (PlanRepository $planRepository) {
                    return $planRepository->createQueryBuilder('p')
                        ->where('p.isActive = :active')
                        ->andWhere('p.isBuyable = :isBuyable')
                        ->setParameter('active', true)
                        ->setParameter('isBuyable', true)
                        ->orderBy('p.priceMonthly', 'ASC')
                        ;
                },
                'expanded' => true,
                'multiple' => false,
                'label' => 'Choisissez votre plan',
                'attr' => [
                    'class' => 'plan-selector-field sr-only',
                ],
                'choice_attr' => function (Plan $plan, $key, $value) {
                    return [
                        'class' => 'plan-card__radio sr-only',
                        'data-plan-id' => $plan->getId(),
                        'data-plan-name' => $plan->getName(),
                        'data-plan-price' => $plan->getFormattedPriceMonthly(),
                        'data-plan-is-free' => $plan->isFree() ? 'true' : 'false',
                        'data-plan-is-popular' => $plan->isPopular() ? 'true' : 'false',
                        'data-plan-max-projects' => $plan->getMaxProjectsLabel(),
                        'data-plan-max-errors' => $plan->getMaxMonthlyErrorsLabel(),
                        'id' => 'registration_form_plan_' . $key,
                    ];
                },
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner un plan',
                    ]),
                ],
            ])
            ->add('acceptTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'J\'accepte les conditions d\'utilisation et la politique de confidentialité',
                'attr' => [
                    'class' => 'checkbox-input sr-only',
                    'id' => 'registration_form_acceptTerms',
                ],
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions d\'utilisation',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'attr' => [
                'class' => 'auth-form auth-form--register',
                'id' => 'registerForm',
                'novalidate' => true,
            ],
        ]);
    }
}
