<?php

namespace App\Form;

use App\Entity\Donation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DonationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', NumberType::class, [
                'label' => 'Amount (TND)',
                'required' => false, // Désactive validation HTML5
                'scale' => 2,
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-paw-orange focus:border-paw-orange',
                    'placeholder' => '0.00',
                ]
            ])
            ->add('date', DateType::class, [
                'label' => 'Date',
                'required' => false, // Désactive validation HTML5
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'dd/MM/yyyy',
                'empty_data' => new \DateTime(),
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-paw-orange focus:border-paw-orange',
                    'placeholder' => 'dd/mm/yyyy'
                ]
            ])
            ->add('donateur', TextType::class, [
                'label' => 'Donor',
                'required' => false, // Désactive validation HTML5
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-paw-orange focus:border-paw-orange',
                    'placeholder' => 'Donor name'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false, // Désactive validation HTML5 - champ optionnel
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-paw-orange focus:border-paw-orange',
                    'placeholder' => 'email@example.com'
                ]
            ])
            ->add('reference', TextType::class, [
                'label' => 'Reference',
                'required' => false, // Désactive validation HTML5
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-paw-orange focus:border-paw-orange',
                    'placeholder' => 'Payment reference'
                ]
            ])
            ->add('statut', CheckboxType::class, [
                'label' => 'Validated',
                'required' => false, // Désactive validation HTML5
                'attr' => [
                    'class' => 'h-4 w-4 text-paw-orange border-gray-300 rounded focus:ring-paw-orange mr-2'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Donation::class,
            'attr' => [
                'novalidate' => 'novalidate', // Désactive validation HTML5
            ]
        ]);
    }
}