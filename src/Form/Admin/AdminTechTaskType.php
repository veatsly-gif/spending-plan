<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\TechTask;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AdminTechTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'form.tech_task_title',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'form.tech_task_description',
                'required' => false,
                'constraints' => [
                    new Length(max: 4000),
                ],
            ]);

        if (true === $options['include_status']) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'form.tech_task_status',
                'choices' => [
                    'form.tech_task_status_option.new' => TechTask::STATUS_NEW,
                    'form.tech_task_status_option.in_progress' => TechTask::STATUS_IN_PROGRESS,
                    'form.tech_task_status_option.in_test' => TechTask::STATUS_IN_TEST,
                    'form.tech_task_status_option.done' => TechTask::STATUS_DONE,
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TechTask::class,
            'include_status' => false,
        ]);

        $resolver->setAllowedTypes('include_status', 'bool');
    }
}
