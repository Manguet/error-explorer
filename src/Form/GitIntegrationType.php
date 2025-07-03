<?php

namespace App\Form;

use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GitIntegrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('repositoryUrl', TextType::class, [
                'label' => 'URL du dépôt Git',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://github.com/user/repo',
                ],
                'help' => 'L\'URL complète de votre dépôt (ex: https://github.com/username/repository)',
            ])
            ->add('gitProvider', ChoiceType::class, [
                'label' => 'Fournisseur Git',
                'choices' => [
                    'GitHub' => 'github',
                    'GitLab' => 'gitlab',
                    'Bitbucket' => 'bitbucket',
                ],
                'required' => false,
                'placeholder' => 'Sélectionnez un fournisseur',
            ])
            ->add('gitAccessToken', PasswordType::class, [
                'label' => 'Token d\'accès',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'ghp_xxxxxxxxxxxx',
                    'autocomplete' => 'off',
                ],
                'help' => 'Token d\'accès personnel avec permissions de lecture du dépôt',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
