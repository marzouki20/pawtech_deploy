<?php

namespace App\Form;

use App\Entity\Adoption;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdoptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('applicantAge', IntegerType::class, [
                'label' => 'Applicant Age',
                'required' => true,
                'attr' => [
                    'min' => 18,
                    'max' => 120,
                    'placeholder' => 'e.g. 28',
                    'inputmode' => 'numeric',
                ],
            ])
            ->add('income', NumberType::class, [
                'label' => 'Income',
                'scale' => 2,
                'required' => true,
                'attr' => [
                    'min' => 0,
                    'max' => 1000000,
                    'step' => '0.01',
                    'placeholder' => 'e.g. 2500',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('housingType', ChoiceType::class, [
                'label' => 'Housing Type',
                'required' => true,
                'placeholder' => 'Select one',
                'choices' => [
                    'Apartment' => 'apartment',
                    'House' => 'house',
                    'Farm' => 'farm',
                    'Other' => 'other',
                ],
            ])
            ->add('hasYard', CheckboxType::class, [
                'label' => 'Has Yard',
                'required' => false,
            ])
            ->add('familySize', IntegerType::class, [
                'label' => 'Family Size',
                'required' => true,
                'attr' => [
                    'min' => 1,
                    'max' => 20,
                    'placeholder' => 'e.g. 3',
                    'inputmode' => 'numeric',
                ],
            ])
            ->add('hoursAwayPerDay', IntegerType::class, [
                'label' => 'Hours Away Per Day',
                'required' => true,
                'attr' => [
                    'min' => 0,
                    'max' => 24,
                    'placeholder' => 'e.g. 6',
                    'inputmode' => 'numeric',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Adoption::class,
        ]);
    }
}
