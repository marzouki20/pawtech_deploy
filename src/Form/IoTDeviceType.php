<?php

namespace App\Form;

use App\Entity\IoTDevice;
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

class IoTDeviceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Device Name',
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g., Temperature Sensor 1'],
            ])
            ->add('deviceType', ChoiceType::class, [
                'label' => 'Device Type',
                'choices' => [
                    'ESP32' => 'ESP32',
                    'ESP8266' => 'ESP8266',
                    'Arduino' => 'Arduino',
                    'Raspberry Pi' => 'Raspberry Pi',
                    'Custom' => 'Custom',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('deviceId', TextType::class, [
                'label' => 'Device ID (Unique)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g., ESP32_001'],
            ])
            ->add('firmwareVersion', TextType::class, [
                'label' => 'Firmware Version',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '1.0.0'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => 'active',
                    'Inactive' => 'inactive',
                    'Maintenance' => 'maintenance',
                    'Error' => 'error',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('wifiSsid', TextType::class, [
                'label' => 'WiFi SSID',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Your WiFi Network'],
            ])
            ->add('wifiPassword', PasswordType::class, [
                'label' => 'WiFi Password',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'WiFi Password'],
            ])
            ->add('apiServerUrl', TextType::class, [
                'label' => 'API Server URL',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'http://192.168.1.117:8000'],
            ])
            ->add('apiEndpoint', TextType::class, [
                'label' => 'API Endpoint',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '/admin/stations/api/iot/data'],
            ])
            ->add('reportingInterval', IntegerType::class, [
                'label' => 'Reporting Interval (seconds)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '60'],
            ])
            ->add('heartbeatInterval', IntegerType::class, [
                'label' => 'Heartbeat Interval (seconds)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '300'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => IoTDevice::class,
        ]);
    }
}
