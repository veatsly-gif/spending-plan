<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Currency;
use App\Entity\SpendingPlan;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

final class AdminSpendingPlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('planType', ChoiceType::class, [
                'label' => 'Plan type',
                'choices' => [
                    'Custom' => SpendingPlan::PLAN_TYPE_CUSTOM,
                    'Weekday' => SpendingPlan::PLAN_TYPE_WEEKDAY,
                    'Weekend' => SpendingPlan::PLAN_TYPE_WEEKEND,
                    'Regular spends' => SpendingPlan::PLAN_TYPE_REGULAR,
                    'Planned spends' => SpendingPlan::PLAN_TYPE_PLANNED,
                ],
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'Date from',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'Date to',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('limitAmount', NumberType::class, [
                'label' => 'Limit amount',
                'scale' => 2,
                'constraints' => [
                    new Range(min: 0),
                ],
            ])
            ->add('currency', EntityType::class, [
                'class' => Currency::class,
                'choice_label' => static function (Currency $currency): string {
                    return $currency->getCode();
                },
            ])
            ->add('weight', IntegerType::class, [
                'constraints' => [
                    new Range(min: 0, max: 100000),
                ],
            ])
            ->add('isSystem', CheckboxType::class, [
                'label' => 'System/default plan',
                'required' => false,
            ])
            ->add('note', TextareaType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SpendingPlan::class,
        ]);
    }
}
