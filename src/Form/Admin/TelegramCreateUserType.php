<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use App\Validation\UserPasswordRequirements;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TelegramCreateUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'form.username',
                'constraints' => [new NotBlank(), new Length(min: 3)],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'form.password',
                'constraints' => UserPasswordRequirements::constraints(),
            ]);
    }
}
