<?php

namespace App\Form\Profile;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

class DeleteAccountFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'attr' => [
                    'class' => 'form__input',
                    'autocomplete' => 'current-password',
                    'placeholder' => 'Entrez votre mot de passe'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer votre mot de passe',
                    ]),
                ],
            ])
            ->add('confirmation', TextType::class, [
                'label' => 'Confirmation',
                'mapped' => false,
                'help' => 'Tapez "SUPPRIMER MON COMPTE" pour confirmer',
                'attr' => [
                    'class' => 'form__input',
                    'placeholder' => 'SUPPRIMER MON COMPTE'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez confirmer la suppression',
                    ]),
                    new EqualTo([
                        'value' => 'SUPPRIMER MON COMPTE',
                        'message' => 'Veuillez taper exactement "SUPPRIMER MON COMPTE"',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}