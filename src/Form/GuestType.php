<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Guest;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GuestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['placeholder' => 'Prénom de l\'invité']
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Nom de l\'invité']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['placeholder' => 'email@example.com']
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['placeholder' => '+216 XX XXX XXX']
            ])
            ->add('organisation', TextType::class, [
                'label' => 'Organisation',
                'required' => false,
                'attr' => ['placeholder' => 'Entreprise ou organisation']
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Biographie',
                'required' => false,
                'attr' => ['placeholder' => 'Courte biographie...', 'rows' => 3]
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => array_combine(Guest::ROLES, Guest::ROLES),
                'placeholder' => 'Sélectionner un rôle'
            ])
            ->add('evenement', EntityType::class, [
                'class' => Evenement::class,
                'choice_label' => 'titre',
                'label' => 'Événement',
                'placeholder' => 'Sélectionner un événement',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Guest::class,
        ]);
    }
}
