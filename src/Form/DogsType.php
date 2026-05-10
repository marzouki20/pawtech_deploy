<?php

namespace App\Form;

use App\Entity\Dogs;
use App\Entity\Ziptag;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DogsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add('name')
            ->add('age')
            ->add('breed')
            ->add('gender')
            ->add('size')
            ->add('weight')
            ->add('vaccinated')
            ->add('friendly_with_kids')
            ->add('friendly_with_dogs')
            ->add('friendly_with_cats')
            ->add('health_status')
            ->add('adoption_status')
            ->add('arrival_date', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('image', HiddenType::class)
            ->add('description')
            ->add('microchip_number')
            ->add('ziptag', EntityType::class, [
                'class' => Ziptag::class,
                'choices' => $options['ziptag_choices'] ?? [],
                'choice_label' => function (Ziptag $ziptag) {
                    return $ziptag->getModel() . ' - ' . $ziptag->getSerialNumber();
                },
                'required' => false,
                'placeholder' => 'None',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dogs::class,
            'ziptag_choices' => [],
        ]);
    }
}
