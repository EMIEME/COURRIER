<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordRequired = (bool) $options['password_required'];

        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nom complet',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('service', TextType::class, [
                'label' => 'Service',
                'required' => false,
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Permissions',
                'choices' => [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Secrétariat' => 'ROLE_SECRETARIAT',
                    'Utilisateur standard' => 'ROLE_USER',
                    'Voir les courriers' => 'ROLE_COURRIER_VIEW',
                    'Créer et modifier les courriers' => 'ROLE_COURRIER_EDIT',
                    'Supprimer les courriers' => 'ROLE_COURRIER_DELETE',
                    'Valider / changer le statut' => 'ROLE_COURRIER_VALIDATE',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $passwordRequired ? 'Mot de passe' : 'Nouveau mot de passe',
                'mapped' => false,
                'required' => $passwordRequired,
                'constraints' => array_filter([
                    $passwordRequired ? new NotBlank(message: 'Le mot de passe est obligatoire.') : null,
                    new Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caracteres.'),
                ]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'password_required' => true,
        ]);
    }
}
