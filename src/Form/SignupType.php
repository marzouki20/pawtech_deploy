<?php
// src/Form/SignupType.php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

class SignupType extends AbstractType
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
            // Hidden field that stores Base64 face image
            ->add('user_face', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, [
                'mapped' => true,
                'required' => false,
            ])
            

            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'options' => ['attr' => ['class' => 'form-input']],

                'required' => false,

                'first_options'  => [
                    'label' => 'Password',
                    'attr' => [
                        'placeholder' => '••••••••',
                        'class' => 'form-input',
                        'id' => 'password'
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => [
                        'placeholder' => '••••••••',
                        'class' => 'form-input',
                        'id' => 'confirm-password'
                    ],
                ]
                
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'I agree to the Terms of Service and Privacy Policy',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new IsTrue(['message' => 'You must agree to the terms.']),
                ],

            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'signup_item',
        ]);
    }
}