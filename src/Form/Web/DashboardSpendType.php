<?php

declare(strict_types=1);

namespace App\Form\Web;

use App\DTO\Controller\Web\DashboardSpendDraftDto;
use App\Entity\Currency;
use App\Entity\SpendingPlan;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DashboardSpendType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', NumberType::class, [
                'label' => 'form.amount',
                'scale' => 2,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('currency', EntityType::class, [
                'label' => 'form.currency',
                'class' => Currency::class,
                'choice_label' => 'code',
                'placeholder' => 'form.choose_currency',
            ])
            ->add('spendingPlan', EntityType::class, [
                'label' => 'form.spending_plan',
                'class' => SpendingPlan::class,
                'choice_label' => static function (SpendingPlan $spendingPlan) use ($options): string {
                    if (true === $options['compact_spending_plan_labels']) {
                        return $spendingPlan->getName();
                    }

                    return sprintf(
                        '%s (%s - %s)',
                        $spendingPlan->getName(),
                        $spendingPlan->getDateFrom()->format('Y-m-d'),
                        $spendingPlan->getDateTo()->format('Y-m-d')
                    );
                },
                'choices' => $options['spending_plan_choices'],
                'placeholder' => 'form.choose_spending_plan',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('spendDate', DateType::class, [
                'label' => 'form.spend_date',
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('comment', TextType::class, [
                'label' => 'form.comment',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DashboardSpendDraftDto::class,
            'spending_plan_choices' => [],
            'compact_spending_plan_labels' => false,
        ]);

        $resolver->setAllowedTypes('spending_plan_choices', 'array');
        $resolver->setAllowedTypes('compact_spending_plan_labels', 'bool');
    }
}
