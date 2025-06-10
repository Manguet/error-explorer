<?php

namespace App\Form;

use App\Entity\TeamMember;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TeamInviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'placeholder' => 'utilisateur@exemple.com',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'adresse email est obligatoire']),
                    new Email(['message' => 'L\'adresse email n\'est pas valide'])
                ]
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Administrateur' => TeamMember::ROLE_ADMIN,
                    'Membre' => TeamMember::ROLE_MEMBER,
                    'Visualisateur' => TeamMember::ROLE_VIEWER,
                ],
                'data' => TeamMember::ROLE_MEMBER, // Default role
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le rôle est obligatoire'])
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message personnalisé (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ajoutez un message personnel à votre invitation...',
                    'class' => 'form-control',
                    'rows' => 3
                ],
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'Le message ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // No data class since this is just for invitation
        ]);
    }
}