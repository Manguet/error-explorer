<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre prénom'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'votre@email.com',
                    'readonly' => true
                ]
            ])
            ->add('company', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom de votre entreprise (optionnel)'
                ]
            ])
            ->add('emailAlertsEnabled', CheckboxType::class, [
                'label' => 'Recevoir les alertes par email',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('criticalAlertsEnabled', CheckboxType::class, [
                'label' => 'Recevoir les alertes critiques',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('weeklyReportsEnabled', CheckboxType::class, [
                'label' => 'Recevoir les rapports hebdomadaires',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer les modifications',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}