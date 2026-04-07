<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserMetadata;
use App\Repository\UserMetadataRepository;

final readonly class UserMetadataService
{
    public function __construct(
        private UserMetadataRepository $userMetadataRepository,
    ) {
    }

    /**
     * Get user metadata, creating it if it doesn't exist.
     */
    public function getUserMetadata(User $user): UserMetadata
    {
        $metadata = $this->userMetadataRepository->findByUser($user);

        if (!$metadata instanceof UserMetadata) {
            $metadata = new UserMetadata();
            $metadata->setUser($user);
            $metadata->setPreferences([
                'language' => 'en',
                'theme' => 'light',
            ]);
            $this->userMetadataRepository->save($metadata, true);
        }

        return $metadata;
    }

    /**
     * Get user preferences as an array.
     *
     * @return array{language: string, theme: string}
     */
    public function getPreferences(User $user): array
    {
        $metadata = $this->getUserMetadata($user);
        $preferences = $metadata->getPreferences();

        return [
            'language' => $preferences['language'] ?? 'en',
            'theme' => $preferences['theme'] ?? 'light',
        ];
    }

    /**
     * Update a specific preference for a user.
     */
    public function updatePreference(User $user, string $key, mixed $value): UserMetadata
    {
        $metadata = $this->getUserMetadata($user);
        $metadata->setPreference($key, $value);
        $this->userMetadataRepository->save($metadata, true);

        return $metadata;
    }

    /**
     * Update multiple preferences for a user.
     */
    public function updatePreferences(User $user, array $preferences): UserMetadata
    {
        $metadata = $this->getUserMetadata($user);
        $existingPreferences = $metadata->getPreferences();
        $mergedPreferences = array_merge($existingPreferences, $preferences);
        $metadata->setPreferences($mergedPreferences);
        $this->userMetadataRepository->save($metadata, true);

        return $metadata;
    }

    /**
     * Get a specific preference value for a user.
     */
    public function getPreference(User $user, string $key, mixed $default = null): mixed
    {
        $metadata = $this->getUserMetadata($user);

        return $metadata->getPreference($key, $default);
    }
}
