<?php

namespace App\Form;

use App\Entity\Adoption;
use App\Entity\Dogs;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdoptionAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => static function (User $user): string {
                    return sprintf('%s (%s)', $user->getNom(), $user->getEmail());
                },
                'placeholder' => 'Select applicant',
                'required' => true,
            ])
            ->add('dog', EntityType::class, [
                'class' => Dogs::class,
                'choice_label' => static function (Dogs $dog): string {
                    return sprintf('%s (#%d)', $dog->getName(), $dog->getId());
                },
                'placeholder' => 'Select dog',
                'required' => true,
            ])
            ->add('createdAt', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => true,
                'input' => 'datetime_immutable',
                'attr' => [
                    'max' => (new \DateTimeImmutable())->format('Y-m-d\\TH:i'),
                ],
            ])
            ->add('applicantAge', IntegerType::class, [
                'required' => true,
                'attr' => [
                    'min' => 18,
                    'max' => 120,
                    'inputmode' => 'numeric',
                ],
            ])
            ->add('income', NumberType::class, [
                'scale' => 2,
                'required' => true,
                'attr' => [
                    'min' => 0,
                    'max' => 1000000,
                    'step' => '0.01',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('housingType', ChoiceType::class, [
                'choices' => [
                    'Apartment' => 'apartment',
                    'House' => 'house',
                    'Farm' => 'farm',
                    'Other' => 'other',
                ],
                'required' => true,
                'placeholder' => 'Select one',
            ])
            ->add('hasYard', CheckboxType::class, [
                'required' => false,
            ])
            ->add('familySize', IntegerType::class, [
                'required' => true,
                'attr' => [
                    'min' => 1,
                    'max' => 20,
                    'inputmode' => 'numeric',
                ],
            ])
            ->add('hoursAwayPerDay', IntegerType::class, [
                'required' => true,
                'attr' => [
                    'min' => 0,
                    'max' => 24,
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
