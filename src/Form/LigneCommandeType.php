<?php

namespace App\Form;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LigneCommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'nom',
                'placeholder' => 'Select a product',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-paw-orange focus:border-paw-orange'
                ]
            ])
            ->add('commande', EntityType::class, [
                'class' => Commande::class,
                'choice_label' => function(Commande $commande) {
                    return 'Order #' . $commande->getId() . ' - ' . $commande->getDate()->format('d/m/Y');
                },
                'placeholder' => 'Select an order',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-paw-orange focus:border-paw-orange'
                ]
            ])
            ->add('quantite', IntegerType::class, [
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-paw-orange focus:border-paw-orange',
                    'placeholder' => '1'
                ]
            ])
            ->add('prixUnitaire', NumberType::class, [
                'scale' => 2,
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-paw-orange focus:border-paw-orange',
                    'placeholder' => '0.00'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LigneCommande::class,
        ]);
    }
}
