<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Validation\UserPasswordRequirements;

final class AdminUserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'form.username',
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'form.roles',
                'choices' => [
                    'form.role.admin' => 'ROLE_ADMIN',
                    'form.role.user' => 'ROLE_USER',
                    'form.role.incomer' => 'ROLE_INCOMER',
                ],
                'multiple' => true,
                'expanded' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'form.passwords_must_match',
                'first_options' => ['label' => 'form.password'],
                'second_options' => ['label' => 'form.repeat_password'],
                'constraints' => UserPasswordRequirements::constraints(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
