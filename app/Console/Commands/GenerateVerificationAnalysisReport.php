<?php

namespace App\Console\Commands;

use App\Models\VerificationAttempt;
use Illuminate\Console\Command;

class GenerateVerificationAnalysisReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analysis:verification-report
        {dataset? : Path to JSON dataset file (omit when using --source=db)}
        {--format=table : Output format (table|json)}
        {--source=json : Data source (json|db)}
        {--from= : Start date/time filter for DB source (Y-m-d or full datetime)}
        {--to= : End date/time filter for DB source (Y-m-d or full datetime)}
        {--scenario= : Optional scenario filter}
        {--method= : Optional method filter (otp|biometric|hybrid)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate TAR/FAR/attack/time/failure metrics for OTP, biometric, and hybrid signing verification experiments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = (string) $this->option('source');
        if (!in_array($source, ['json', 'db'], true)) {
            $this->error('Invalid --source value. Use json or db.');
            return self::FAILURE;
        }

        $attempts = $source === 'db'
            ? $this->loadAttemptsFromDatabase()
            : $this->loadAttemptsFromJson();

        if ($attempts === null) {
            return self::FAILURE;
        }

        if (empty($attempts)) {
            $this->warn('No records found for the selected dataset/filter.');
            return self::SUCCESS;
        }

        $methods = ['otp', 'biometric', 'hybrid'];
        $byMethod = [];

        foreach ($methods as $method) {
            $methodAttempts = array_values(array_filter($attempts, fn ($item) => ($item['method'] ?? null) === $method));
            $byMethod[$method] = $this->computeMetrics($methodAttempts);
        }

        $researchAnswers = $this->answerResearchQuestions($attempts, $byMethod);

        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'source' => $source,
                'filters' => [
                    'from' => $this->option('from'),
                    'to' => $this->option('to'),
                    'scenario' => $this->option('scenario'),
                    'method' => $this->option('method'),
                ],
                'metrics' => $byMethod,
                'research_answers' => $researchAnswers,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($byMethod as $method => $metrics) {
            $rows[] = [
                strtoupper($method),
                $this->percentage($metrics['tar']),
                $this->percentage($metrics['far']),
                $this->percentage($metrics['attack_success_rate']),
                $this->milliseconds($metrics['mean_completion_time_ms']),
                $this->percentage($metrics['verification_failure_rate']),
                $metrics['total_attempts'],
            ];
        }

        $this->info('Source: ' . strtoupper($source));
        $this->table([
            'Method',
            'TAR',
            'FAR',
            'Attack Success',
            'Completion Time',
            'Verification Failure',
            'Attempts',
        ], $rows);

        $this->newLine();
        $this->info('Research question summary');
        foreach ($researchAnswers as $index => $answer) {
            $this->line(($index + 1) . '. ' . $answer);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function loadAttemptsFromJson(): ?array
    {
        $datasetPath = $this->argument('dataset');
        if (!$datasetPath) {
            $this->error('dataset argument is required when --source=json.');
            return null;
        }

        $resolvedPath = $this->resolvePath((string) $datasetPath);

        if (!is_file($resolvedPath)) {
            $this->error("Dataset file not found: {$datasetPath}");
            return null;
        }

        $raw = file_get_contents($resolvedPath);
        $attempts = json_decode((string) $raw, true);

        if (!is_array($attempts)) {
            $this->error('Dataset must be a JSON array of attempt records.');
            return null;
        }

        return array_values($attempts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadAttemptsFromDatabase(): array
    {
        $query = VerificationAttempt::query();

        if ($this->option('from')) {
            $query->where('created_at', '>=', $this->option('from'));
        }

        if ($this->option('to')) {
            $query->where('created_at', '<=', $this->option('to'));
        }

        if ($this->option('scenario')) {
            $query->where('scenario', $this->option('scenario'));
        }

        if ($this->option('method')) {
            $query->where('method', $this->option('method'));
        }

        return $query
            ->orderBy('created_at')
            ->get()
            ->map(fn (VerificationAttempt $row) => [
                'attempt_id' => $row->attempt_id,
                'method' => $row->method,
                'scenario' => $row->scenario,
                'is_legitimate' => $row->is_legitimate,
                'verification_passed' => $row->verification_passed,
                'attack_succeeded' => $row->attack_succeeded,
                'completion_time_ms' => $row->completion_time_ms,
                'failure_cause' => $row->failure_cause,
            ])
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $attempts
     * @return array<string, float|int>
     */
    private function computeMetrics(array $attempts): array
    {
        $knownLegitimacy = array_values(array_filter($attempts, fn ($a) => array_key_exists('is_legitimate', $a) && $a['is_legitimate'] !== null));
        $legitimate = array_values(array_filter($knownLegitimacy, fn ($a) => (bool) $a['is_legitimate'] === true));
        $unauthorized = array_values(array_filter($knownLegitimacy, fn ($a) => (bool) $a['is_legitimate'] === false));

        $legitimateAccepted = count(array_filter($legitimate, fn ($a) => (bool) ($a['verification_passed'] ?? false)));
        $unauthorizedAccepted = count(array_filter($unauthorized, fn ($a) => (bool) ($a['verification_passed'] ?? false)));
        $attackSucceeded = count(array_filter($unauthorized, fn ($a) => (bool) ($a['attack_succeeded'] ?? false)));

        $completionTimes = array_values(array_map(
            fn ($a) => (float) ($a['completion_time_ms'] ?? 0),
            array_filter($attempts, fn ($a) => isset($a['completion_time_ms']) && $a['completion_time_ms'] !== null)
        ));

        $legitimateFailed = count(array_filter($legitimate, fn ($a) => !(bool) ($a['verification_passed'] ?? false)));

        return [
            'tar' => $this->safeRate($legitimateAccepted, count($legitimate)),
            'far' => $this->safeRate($unauthorizedAccepted, count($unauthorized)),
            'attack_success_rate' => $this->safeRate($attackSucceeded, count($unauthorized)),
            'mean_completion_time_ms' => empty($completionTimes) ? 0 : array_sum($completionTimes) / count($completionTimes),
            'verification_failure_rate' => $this->safeRate($legitimateFailed, count($legitimate)),
            'total_attempts' => count($attempts),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $attempts
     * @param array<string, array<string, float|int>> $byMethod
     * @return array<int, string>
     */
    private function answerResearchQuestions(array $attempts, array $byMethod): array
    {
        $otpCompromised = array_values(array_filter($attempts, function ($attempt) {
            return ($attempt['method'] ?? null) === 'otp'
                && ($attempt['scenario'] ?? null) === 'compromised_channel';
        }));

        $otpCompromisedMetrics = $this->computeMetrics($otpCompromised);

        $biometricPresentation = array_values(array_filter($attempts, function ($attempt) {
            return ($attempt['method'] ?? null) === 'biometric'
                && ($attempt['scenario'] ?? null) === 'presentation_attack';
        }));

        $biometricPresentationMetrics = $this->computeMetrics($biometricPresentation);

        $otpFar = (float) ($byMethod['otp']['far'] ?? 0);
        $hybridFar = (float) ($byMethod['hybrid']['far'] ?? 0);
        $otpAttack = (float) ($byMethod['otp']['attack_success_rate'] ?? 0);
        $hybridAttack = (float) ($byMethod['hybrid']['attack_success_rate'] ?? 0);

        $answerOne = sprintf(
            'OTP under compromised-channel scenarios shows FAR=%s and attack success=%s (n=%d).',
            $this->percentage($otpCompromisedMetrics['far']),
            $this->percentage($otpCompromisedMetrics['attack_success_rate']),
            (int) $otpCompromisedMetrics['total_attempts']
        );

        $answerTwo = sprintf(
            'Gesture-based liveness under presentation attacks shows FAR=%s and attack success=%s (n=%d).',
            $this->percentage($biometricPresentationMetrics['far']),
            $this->percentage($biometricPresentationMetrics['attack_success_rate']),
            (int) $biometricPresentationMetrics['total_attempts']
        );

        $answerThree = sprintf(
            'Hybrid vs OTP: FAR %s -> %s, attack success %s -> %s, indicating %s identity assurance.',
            $this->percentage($otpFar),
            $this->percentage($hybridFar),
            $this->percentage($otpAttack),
            $this->percentage($hybridAttack),
            ($hybridFar <= $otpFar && $hybridAttack <= $otpAttack) ? 'stronger' : 'mixed'
        );

        return [$answerOne, $answerTwo, $answerThree];
    }

    private function safeRate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return $numerator / $denominator;
    }

    /**
     * @param float|int $value
     */
    private function percentage($value): string
    {
        return number_format(((float) $value) * 100, 2) . '%';
    }

    /**
     * @param float|int $value
     */
    private function milliseconds($value): string
    {
        return number_format((float) $value, 2) . ' ms';
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\/', $path)) {
            return $path;
        }

        return base_path($path);
    }
}
