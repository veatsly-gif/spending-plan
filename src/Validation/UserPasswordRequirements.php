<?php

declare(strict_types=1);

namespace App\Validation;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * Shared rules for user-chosen passwords (admin forms). Uses Symfony's PasswordStrength (entropy-based).
 */
final class UserPasswordRequirements
{
    /**
     * @return list<object>
     */
    public static function constraints(): array
    {
        return [
            new NotBlank(),
            new Length(min: 8, max: 4096),
        ];
    }
}
