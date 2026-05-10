<?php
// src/Form/SuiviType.php

namespace App\Form;

use App\Entity\Suivi;
use App\Entity\Consultation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SuiviType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('etat', ChoiceType::class, [
                'label' => 'État',
                'choices' => [
                    'Planifié' => 'Planifié',
                    'En cours' => 'En cours',
                    'Terminé' => 'Terminé',
                    'Annulé' => 'Annulé'
                ],
                'placeholder' => 'Sélectionnez un état',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-paw-orange focus:border-paw-orange'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de suivi',
                'choices' => [
                    'Routine' => 'Routine',
                    'Vaccination' => 'Vaccination',
                    'Contrôle' => 'Contrôle',
                    'Urgence' => 'Urgence',
                    'Chirurgie' => 'Chirurgie',
                    'Dermatologie' => 'Dermatologie',
                    'Autre' => 'Autre'
                ],
                'placeholder' => 'Sélectionnez un type de suivi',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-paw-orange focus:border-paw-orange'
                ]
            ])
            ->add('recommandation', TextareaType::class, [
                'label' => 'Recommandation',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-paw-orange focus:border-paw-orange',
                    'rows' => 5,
                    'placeholder' => 'Recommandations et observations...'
                ]
            ])
            ->add('prochaineVisite', DateTimeType::class, [
                'label' => 'Date et heure de la prochaine visite',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true, // CHANGÉ: false → true
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-paw-orange focus:border-paw-orange'
                ]
            ])
            ->add('consultation', EntityType::class, [
                'label' => 'Consultation associée',
                'class' => Consultation::class,
                'choice_label' => function (Consultation $consultation) {
                    $date = $consultation->getDate()->format('d/m/Y');
                    $type = $consultation->getType();
                    $dog = $consultation->getDog() ? $consultation->getDog()->getName() : 'N/A';
                    return "#{$consultation->getId()} - {$date} - {$type} ({$dog})";
                },
                'placeholder' => 'Sélectionnez une consultation',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-paw-orange focus:border-paw-orange'
                ]
            ])
            
                
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Suivi::class,
        ]);
    }
}