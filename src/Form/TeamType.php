<?php

namespace App\Form;

use App\Entity\Team;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class TeamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'équipe',
                'attr' => [
                    'placeholder' => 'Ex: Équipe Frontend, DevOps Team...',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom de l\'équipe est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description de l\'équipe, ses objectifs...',
                    'class' => 'form-control',
                    'rows' => 3
                ],
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('maxMembers', IntegerType::class, [
                'label' => 'Nombre maximum de membres',
                'attr' => [
                    'min' => 1,
                    'max' => 100,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nombre maximum de membres est obligatoire']),
                    new Range([
                        'min' => 1,
                        'max' => 100,
                        'notInRangeMessage' => 'Une équipe doit avoir au moins {{ limit }} membre et ne peut pas dépasser {{ limit }} membres',
                    ])
                ]
            ])
            ->add('maxProjects', IntegerType::class, [
                'label' => 'Nombre maximum de projets',
                'attr' => [
                    'min' => 1,
                    'max' => 50,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nombre maximum de projets est obligatoire']),
                    new Range([
                        'min' => 1,
                        'max' => 50,
                        'notInRangeMessage' => 'Une équipe doit pouvoir gérer au moins {{ limit }} projet et ne peut pas dépasser {{ limit }} projets',
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Team::class,
        ]);
    }
}
