<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Titre de l\'événement']
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => array_combine(Evenement::TYPES, Evenement::TYPES),
                'placeholder' => 'Sélectionner un type'
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'attr' => ['placeholder' => 'Adresse ou nom du lieu']
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'attr' => ['placeholder' => 'Ville']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['placeholder' => 'Description de l\'événement', 'rows' => 3]
            ])
            ->add('capaciteMax', IntegerType::class, [
                'label' => 'Capacité maximale',
                'required' => false,
                'attr' => ['placeholder' => 'Laisser vide pour illimité', 'min' => 1]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_combine(Evenement::STATUTS, Evenement::STATUTS),
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG ou WebP)',
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
