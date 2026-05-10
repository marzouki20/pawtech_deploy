<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Participation;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'label' => 'Participant',
                'placeholder' => 'Sélectionner un participant',
            ])
            ->add('evenement', EntityType::class, [
                'class' => Evenement::class,
                'choice_label' => 'titre',
                'label' => 'Événement',
                'placeholder' => 'Sélectionner un événement',
            ])
            ->add('dateParticipation', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date d\'inscription',
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_combine(Participation::STATUTS, Participation::STATUTS),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participation::class,
        ]);
    }
}
