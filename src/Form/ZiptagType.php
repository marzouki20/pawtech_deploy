<?php

namespace App\Form;

use App\Entity\Dogs;
use App\Entity\Ziptag;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ZiptagType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('serialNumber')
            ->add('firmwareVersion')
            ->add('model')
            ->add('dog', EntityType::class, [
                'class' => Dogs::class,
                'choices' => $options['dog_choices'] ?? [],
                'choice_label' => function (Dogs $dog) {
                    return $dog->getName() . ' (#' . $dog->getId() . ')';
                },
                'required' => false,
                'placeholder' => 'None',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ziptag::class,
            'dog_choices' => [],
        ]);
    }
}
