<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom complet *',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le nom est requis'
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins 2 caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser 100 caractères'
                    ])
                ],
                'attr' => [
                    'placeholder' => 'Votre nom complet',
                    'class' => 'form-input'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email *',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'L\'email est requis'
                    ]),
                    new Assert\Email([
                        'message' => 'Veuillez entrer un email valide'
                    ])
                ],
                'attr' => [
                    'placeholder' => 'votre.email@exemple.com',
                    'class' => 'form-input'
                ]
            ])
            ->add('company', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => 'Le nom de l\'entreprise ne peut pas dépasser 100 caractères'
                    ])
                ],
                'attr' => [
                    'placeholder' => 'Nom de votre entreprise (optionnel)',
                    'class' => 'form-input'
                ]
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [
                    new Assert\Regex([
                        'pattern' => '/^[\+]?[0-9\s\-\(\)\.]{8,15}$/',
                        'message' => 'Veuillez entrer un numéro de téléphone valide'
                    ])
                ],
                'attr' => [
                    'placeholder' => '+33 1 23 45 67 89 (optionnel)',
                    'class' => 'form-input'
                ]
            ])
            ->add('subject', ChoiceType::class, [
                'label' => 'Sujet *',
                'choices' => [
                    'Question générale' => 'general',
                    'Support technique' => 'support',
                    'Demande commerciale' => 'sales',
                    'Partenariat' => 'partnership',
                    'Signaler un bug' => 'bug',
                    'Demande de fonctionnalité' => 'feature',
                    'Autre' => 'other'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Veuillez sélectionner un sujet'
                    ])
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message *',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le message est requis'
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 2000,
                        'minMessage' => 'Le message doit contenir au moins 10 caractères',
                        'maxMessage' => 'Le message ne peut pas dépasser 2000 caractères'
                    ])
                ],
                'attr' => [
                    'placeholder' => 'Décrivez votre demande en détail...',
                    'rows' => 6,
                    'class' => 'form-textarea'
                ]
            ])
            ->add('acceptTerms', CheckboxType::class, [
                'label' => 'J\'accepte que mes données soient utilisées pour traiter ma demande de contact selon la politique de confidentialité.',
                'mapped' => false,
                'constraints' => [
                    new Assert\IsTrue([
                        'message' => 'Vous devez accepter le traitement de vos données'
                    ])
                ],
                'attr' => [
                    'class' => 'form-checkbox'
                ]
            ])
            // Honeypot pour les bots
            ->add('website', HiddenType::class, [
                'mapped' => false,
                'attr' => [
                    'style' => 'display: none !important;'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
