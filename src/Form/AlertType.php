<?php

namespace App\Form;

use App\Entity\Alert;
use App\Entity\ObservationStation;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AlertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Technical' => 'TECHNICAL',
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                ],
                'label' => 'Type',
                'required' => true,
                'placeholder' => false,
                'empty_data' => 'TECHNICAL',
            ])
            ->add('message', TextareaType::class, [
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                    'rows' => '3',
                    'placeholder' => 'Entrez le message',
                ],
                'label' => 'Message',
            ])
            ->add('prioritee', IntegerType::class, [
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                    'min' => '1',
                    'step' => '1',
                    'placeholder' => 'Entrez un numéro',
                ],
                'label' => 'Priorité',
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Unread' => 'unread',
                    'Read' => 'read',
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                ],
                'label' => 'Statut',
                'placeholder' => 'Select status',
            ])
            ->add('station', EntityType::class, [
                'class' => ObservationStation::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->orderBy('s.code', 'ASC');
                },
                'choice_label' => 'code',
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                ],
                'label' => 'Station',
                'placeholder' => 'Select station',
                'required' => false,
            ])
            ->add('date', DateTimeType::class, [
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors bg-gray-50 hover:bg-white',
                ],
                'label' => 'Date',
                'widget' => 'single_text',
                'html5' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Alert::class,
        ]);
    }
}
