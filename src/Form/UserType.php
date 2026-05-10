<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', null, [
                'required' => false,
            ])

            ->add('prenom', null, [
                'required' => false,
            ])

            ->add('email', null, [
                'required' => false,
            ])

            ->add('telephone', null, [
                'required' => false,
            ])

            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Client' => 'Client',
                    'Veterinaire' => 'Veterinaire',
                    'Agent Municipale' => 'Agent Municipale',
                    'Admin' => 'Admin',
                ],
                'placeholder' => 'Choose a role',
                'required' => false,
                'error_bubbling' => false,
            ])

            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Actif' => 'Actif',
                    'Inactif' => 'Inactif',
                ],
                'placeholder' => 'Choose a status',
                'required' => false,
                'error_bubbling' => false,
            ])

            ->add('password', null, [
                'required' => false,
            ])
        
            ->add('user_image', FileType::class, [
                'label' => 'User image',
                'mapped' => false,
                'required' => false,
                'error_bubbling' => false,
            ])
            
            ->add('order_number', null, [
                'required' => false,
            ])

            ->add('matricule', null, [
                'required' => false,
            ])

            ->add('zone_affectee', null, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => false,
        ]);
    }
}
