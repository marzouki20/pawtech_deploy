<?php

namespace App\Form;

use App\Entity\IpCamera;
use App\Entity\ObservationStation;
use App\Repository\ObservationStationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IpCameraType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Camera Name',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('ipAddress', TextType::class, [
                'label' => 'IP Address',
                'attr' => ['class' => 'form-control', 'placeholder' => '192.168.1.100'],
            ])
            ->add('port', IntegerType::class, [
                'label' => 'Port',
                'attr' => ['class' => 'form-control', 'value' => 80],
            ])
            ->add('username', TextType::class, [
                'label' => 'Username',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('streamUrl', TextType::class, [
                'label' => 'Stream URL (RTSP/HTTP)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'rtsp://192.168.1.100:554/stream or /stream'],
            ])
            ->add('rtspUrl', TextType::class, [
                'label' => 'RTSP URL',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'rtsp://192.168.1.162:554/stream'],
            ])
            ->add('snapshotUrl', TextType::class, [
                'label' => 'Snapshot URL',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '/snapshot.jpg'],
            ])
            ->add('model', TextType::class, [
                'label' => 'Model',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'QC5-1080P'],
            ])
            ->add('resolution', ChoiceType::class, [
                'label' => 'Resolution',
                'choices' => [
                    '720p' => '720p',
                    '1080p' => '1080p',
                    '2K' => '2K',
                    '4K' => '4K',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => 'active',
                    'Inactive' => 'inactive',
                    'Error' => 'error',
                ],
            ])
            ->add('station', EntityType::class, [
                'label' => 'Associated Station',
                'class' => ObservationStation::class,
                'choice_label' => 'code',
                'required' => false,
                'query_builder' => function (ObservationStationRepository $repo) {
                    return $repo->createQueryBuilder('s')
                        ->orderBy('s.code', 'ASC');
                },
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => IpCamera::class,
        ]);
    }
}
