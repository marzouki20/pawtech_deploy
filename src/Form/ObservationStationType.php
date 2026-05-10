<?php

namespace App\Form;

use App\Entity\ObservationStation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ObservationStationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                    'placeholder' => 'Enter station code',
                ],
                'label' => 'Code',
                'help' => 'Le code doit contenir exactement 6 chiffres.',
            ])
            ->add('zone', TextType::class, [
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                    'placeholder' => 'Enter zone name',
                ],
                'label' => 'Zone',
                'help' => 'La zone doit être de la forme "ghazela_Nord".',
            ])
            ->add('localisation', TextType::class, [
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                    'placeholder' => 'Enter location',
                ],
                'label' => 'Localisation',
                'help' => 'La localisation doit être au format "48.8566, 2.3522".',
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Active' => 'active',
                    'Inactive' => 'inactive',
                    'Maintenance' => 'maintenance',
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                ],
                'label' => 'Statut',
                'placeholder' => 'Select status',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ObservationStation::class,
        ]);
    }
}
