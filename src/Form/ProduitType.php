<?php
// src/Form/ProduitType.php

namespace App\Form;

use App\Entity\Produit;
use App\Entity\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints as Assert;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => [
                    'placeholder' => 'Entrez le nom du produit'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le nom du produit est obligatoire.'
                    ])
                ]
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix',
                'attr' => [
                    'placeholder' => '0.00'
                ],
                'scale' => 2,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le prix est obligatoire.'
                    ]),
                    new Assert\Positive([
                        'message' => 'Le prix doit être supérieur à 0.'
                    ])
                ]
            ])
            ->add('categorie', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Categorie::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionnez une catégorie',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Veuillez sélectionner une catégorie.'
                    ])
                ]
            ])
            ->add('image', FileType::class, [
                'label' => 'Image du produit',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'maxSizeMessage' => 'L\'image est trop lourde ({{ size }} {{ suffix }}). La taille maximale est de {{ limit }} {{ suffix }}',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG, GIF, WEBP).',
                    ])
                ]
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité',
                'attr' => [
                    'placeholder' => '0'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'La quantité est obligatoire.'
                    ]),
                    new Assert\PositiveOrZero([
                        'message' => 'La quantité ne peut pas être négative.'
                    ])
                ]
            ])
            ->add('seuilAlert', IntegerType::class, [
                'label' => 'Seuil d\'alerte',
                'attr' => [
                    'placeholder' => '0'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le seuil d\'alerte est obligatoire.'
                    ]),
                    new Assert\PositiveOrZero([
                        'message' => 'Le seuil d\'alerte ne peut pas être négatif.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
