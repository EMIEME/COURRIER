<?php

namespace App\Form;

use App\Entity\Courrier;
use App\Entity\Destinataire;
use App\Entity\User;
use App\Repository\DestinataireRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CourrierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mailDate', DateType::class, [
                'label' => 'Date du courrier',
                'widget' => 'single_text',
            ])
            ->add('direction', ChoiceType::class, [
                'label' => 'Sens',
                'choices' => Courrier::DIRECTIONS,
            ])
            ->add('senderContact', EntityType::class, [
                'label' => 'Emetteur',
                'class' => Destinataire::class,
                'choice_label' => 'name',
                'placeholder' => '',
                'required' => false,
                'attr' => [
                    'class' => 'single-emetteur-select',
                    'data-autocomplete-select' => 'true',
                ],
                'query_builder' => fn (DestinataireRepository $repository) => $repository->createQueryBuilder('d')->orderBy('d.name', 'ASC'),
            ])
            ->add('destinataires', EntityType::class, [
                'label' => 'Destinataires',
                'class' => Destinataire::class,
                'choice_label' => 'name',
                'multiple' => true,
                'by_reference' => false,
                'required' => false,
                'attr' => [
                    'class' => 'multi-destinataire-select',
                    'data-autocomplete-select' => 'true',
                ],
                'query_builder' => fn (DestinataireRepository $repository) => $repository->createQueryBuilder('d')->orderBy('d.name', 'ASC'),
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => Courrier::TYPES,
                'placeholder' => 'Choisir un type',
            ])
            ->add('subject', TextType::class, [
                'label' => 'Objet',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu / mots-cles',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => Courrier::STATUSES,
            ])
            ->add('assignedTo', EntityType::class, [
                'label' => 'Imputer a',
                'class' => User::class,
                'choice_label' => 'fullName',
                'multiple' => true,
                'by_reference' => false,
                'required' => false,
                'attr' => [
                    'class' => 'multi-imputation-select',
                    'data-autocomplete-select' => 'true',
                ],
                'query_builder' => fn (UserRepository $repository) => $repository->createQueryBuilder('u')->orderBy('u.fullName', 'ASC'),
            ])
            ->add('responseDueAt', DateType::class, [
                'label' => 'Echeance de reponse',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('responseNotes', TextareaType::class, [
                'label' => 'Suivi / reponse',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('attachment', FileType::class, [
                'label' => 'Scan ou PDF',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Ajoutez un PDF ou une image valide.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Courrier::class,
        ]);
    }
}
