<?php

namespace App\Form;

use App\Entity\TeamMember;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TeamMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        
        $builder
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Administrateur' => TeamMember::ROLE_ADMIN,
                    'Membre' => TeamMember::ROLE_MEMBER,
                    'Visualisateur' => TeamMember::ROLE_VIEWER,
                ],
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le rôle est obligatoire'])
                ]
            ]);

        // Only show user selector when creating new membership
        if (!$isEdit) {
            $builder->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'label' => 'Utilisateur',
                'placeholder' => 'Sélectionnez un utilisateur',
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'utilisateur est obligatoire'])
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TeamMember::class,
            'is_edit' => false,
        ]);
    }
}