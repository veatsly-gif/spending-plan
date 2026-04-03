<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use App\Validation\UserPasswordRequirements;

final class AdminUserPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => 'form.passwords_must_match',
            'first_options' => ['label' => 'form.new_password'],
            'second_options' => ['label' => 'form.repeat_password'],
            'constraints' => UserPasswordRequirements::constraints(),
        ]);
    }
}
