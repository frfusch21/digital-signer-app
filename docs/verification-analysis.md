# Verification Analysis Guide (OTP vs Biometric vs Hybrid)

This project includes automated verification-attempt logging and an analysis command to produce paper-ready metrics.

## 1) Automatic data collection

Verification flows now write experiment records to `verification_attempts` with fields aligned to your evaluation metrics.

### Logged verification methods

- `otp` (from OTP verification flow)
- `biometric` (from face detect/liveness/complete verification flow)
- `hybrid` (optional: set `verification_method=hybrid` during biometric calls in combined experiments)

### Optional experiment labels in requests

To support controlled research scenarios, you can pass these optional fields in verification requests:

- `scenario` (e.g., `normal`, `compromised_channel`, `presentation_attack`)
- `is_legitimate` (`true`/`false`) for TAR/FAR experiments
- `verification_method` (`biometric` or `hybrid`) for combined workflows

> If `is_legitimate` is omitted, records are still stored, but TAR/FAR denominators only include attempts where legitimacy is known.

## 2) Dataset format (manual JSON alternative)

You can still run analysis from a JSON file with this schema:

```json
{
  "attempt_id": "A-001",
  "method": "otp",
  "scenario": "compromised_channel",
  "is_legitimate": false,
  "verification_passed": true,
  "attack_succeeded": true,
  "completion_time_ms": 14800,
  "failure_cause": null
}
```

## 3) Run analysis

### Analyze DB-collected experiments (recommended)

```bash
php artisan analysis:verification-report --source=db
```

Filter examples:

```bash
php artisan analysis:verification-report --source=db --from=2026-02-01 --to=2026-02-28
php artisan analysis:verification-report --source=db --method=otp --scenario=compromised_channel
```

### Analyze JSON dataset

```bash
php artisan analysis:verification-report docs/sample-verification-attempts.json --source=json
```

JSON output for external statistics tooling:

```bash
php artisan analysis:verification-report --source=db --format=json
```

## 4) What the command calculates

### Security metrics

- True Acceptance Rate (TAR)
- False Acceptance Rate (FAR)
- Attack Success Rate

### Operational metrics

- Mean signing completion time
- Verification failure rate for legitimate users

## 5) Research-question summaries produced

The command prints concise answers for:

1. OTP performance under `compromised_channel`
2. Biometric liveness performance under `presentation_attack`
3. Hybrid-vs-OTP comparative identity assurance

This maps collected app data directly to the paper questions without manual spreadsheet aggregation.
