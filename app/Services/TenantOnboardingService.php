<?php

namespace App\Services;

use App\Models\Province;
use App\Models\TenantOnboarding;

class TenantOnboardingService
{
    /**
     * Ordered onboarding steps from provisioning to activation.
     */
    public const STEPS = [
        'provisioning',
        'configuration',
        'membership_setup',
        'feature_flags',
        'smoke_validation',
        'activation',
    ];

    public function getOrCreateForProvince(Province $province, int $createdBy): TenantOnboarding
    {
        return TenantOnboarding::firstOrCreate(
            ['province_id' => $province->id],
            [
                'status' => 'pending',
                'current_step' => self::STEPS[0],
                'completed_steps' => [],
                'created_by' => $createdBy,
            ]
        );
    }

    public function completeStep(TenantOnboarding $onboarding, string $step): TenantOnboarding
    {
        if (!in_array($step, self::STEPS, true)) {
            throw new \InvalidArgumentException("Unknown onboarding step: {$step}");
        }

        $completed = collect((array) $onboarding->completed_steps)->push($step)->unique()->values()->all();
        $currentIndex = array_search($step, self::STEPS, true);
        $nextStep = self::STEPS[$currentIndex + 1] ?? null;

        $onboarding->update([
            'completed_steps' => $completed,
            'current_step' => $nextStep,
            'status' => $nextStep ? 'in_progress' : 'active',
            'activated_at' => $nextStep ? null : now(),
        ]);

        return $onboarding->fresh();
    }
}

