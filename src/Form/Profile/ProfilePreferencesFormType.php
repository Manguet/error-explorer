<?php

namespace App\Form\Profile;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfilePreferencesFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emailAlertsEnabled', CheckboxType::class, [
                'label' => 'Recevoir des alertes par email',
                'help' => 'Recevez des notifications par email lorsque de nouvelles erreurs sont détectées',
                'required' => false,
                'attr' => [
                    'class' => 'form__checkbox',
                ],
                'label_attr' => [
                    'class' => 'form__checkbox-label',
                ],
            ])
            ->add('criticalAlertsEnabled', CheckboxType::class, [
                'label' => 'Alertes critiques uniquement',
                'help' => 'Ne recevoir que les alertes pour les erreurs critiques',
                'required' => false,
                'attr' => [
                    'class' => 'form__checkbox',
                ],
                'label_attr' => [
                    'class' => 'form__checkbox-label',
                ],
            ])
            ->add('weeklyReportsEnabled', CheckboxType::class, [
                'label' => 'Rapports hebdomadaires',
                'help' => 'Recevoir un résumé hebdomadaire de vos erreurs',
                'required' => false,
                'attr' => [
                    'class' => 'form__checkbox',
                ],
                'label_attr' => [
                    'class' => 'form__checkbox-label',
                ],
            ])
            ->add('loginNotificationsEnabled', CheckboxType::class, [
                'label' => 'Notifications de connexion',
                'help' => 'Être notifié lors de nouvelles connexions à votre compte',
                'required' => false,
                'attr' => [
                    'class' => 'form__checkbox',
                ],
                'label_attr' => [
                    'class' => 'form__checkbox-label',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}