<?php

namespace App\Services;

use App\Models\VerificationAttempt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VerificationExperimentLogger
{
    /**
     * @param array<string, mixed> $data
     */
    public function log(array $data): void
    {
        try {
            $isLegitimate = $this->normalizeNullableBool($data['is_legitimate'] ?? null);
            $verificationPassed = (bool) ($data['verification_passed'] ?? false);

            VerificationAttempt::create([
                'attempt_id' => $data['attempt_id'] ?? (string) Str::uuid(),
                'user_id' => $data['user_id'] ?? null,
                'method' => $data['method'] ?? 'otp',
                'scenario' => $data['scenario'] ?? 'normal',
                'is_legitimate' => $isLegitimate,
                'verification_passed' => $verificationPassed,
                'attack_succeeded' => array_key_exists('attack_succeeded', $data)
                    ? (bool) $data['attack_succeeded']
                    : ($isLegitimate === false && $verificationPassed),
                'completion_time_ms' => isset($data['completion_time_ms']) ? (int) $data['completion_time_ms'] : null,
                'failure_cause' => $data['failure_cause'] ?? null,
                'metadata' => isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Verification experiment logging failed: ' . $e->getMessage());
        }
    }

    /**
     * @param mixed $value
     */
    private function normalizeNullableBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $normalized;
    }
}
