<?php

namespace App\Form;

use App\Entity\Courrier;
use App\Entity\Destinataire;
use App\Entity\User;
use App\Repository\CourrierRepository;
use App\Repository\DestinataireRepository;
use App\Repository\UserRepository;
use App\Service\CourrierListProvider;
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
    public function __construct(private readonly CourrierListProvider $listProvider)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $courrier = $builder->getData();
        $currentDirection = $courrier instanceof Courrier ? $courrier->getDirection() : null;
        $currentStatus = $courrier instanceof Courrier ? $courrier->getStatus() : null;
        $currentLocalisation = $courrier instanceof Courrier ? $courrier->getLocalisation() : null;
        $canValidate = (bool) $options['can_validate'];

        $builder
            ->add('mailDate', DateType::class, [
                'label' => 'Date du courrier',
                'widget' => 'single_text',
            ])
            ->add('direction', ChoiceType::class, [
                'label' => 'Nature',
                'choices' => $this->listProvider->natureChoices($currentDirection),
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
            ->add('reference', TextType::class, [
                'label' => 'Reference',
            ])
            ->add('replyTo', EntityType::class, [
                'label' => 'Réponse au courrier',
                'class' => Courrier::class,
                'choice_label' => fn (Courrier $courrier) => sprintf('%s - %s', $courrier->getReference(), $courrier->getSubject()),
                'placeholder' => '',
                'required' => false,
                'attr' => [
                    'class' => 'reply-to-select',
                    'data-autocomplete-select' => 'true',
                ],
                'query_builder' => function (CourrierRepository $repository) use ($options) {
                    $qb = $repository->createQueryBuilder('c')
                        ->orderBy('c.reference', 'DESC');
                    $currentCourrier = $options['current_courrier'];

                    if ($currentCourrier instanceof Courrier && $currentCourrier->getId()) {
                        $qb->andWhere('c.id != :currentCourrier')
                            ->setParameter('currentCourrier', $currentCourrier->getId());
                    }

                    return $qb;
                },
            ])
            ->add('subject', TextType::class, [
                'label' => 'Objet',
            ])
            ->add('localisation', ChoiceType::class, [
                'label' => 'Localisation',
                'choices' => $this->listProvider->localisationChoices($currentLocalisation),
                'placeholder' => 'Choisir une localisation',
                'required' => false,
                'help' => 'Boite ou lieu de rangement du courrier physique.',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu / mots-cles',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => $this->listProvider->statusChoices($currentStatus),
                'disabled' => !$canValidate,
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
            'current_courrier' => null,
            'can_validate' => false,
        ]);

        $resolver->setAllowedTypes('current_courrier', [Courrier::class, 'null']);
        $resolver->setAllowedTypes('can_validate', 'bool');
    }
}
