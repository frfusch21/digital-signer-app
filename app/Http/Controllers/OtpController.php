<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Otp;
use App\Models\User; // Added User model import
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Mail\OtpMail;
use App\Http\Middleware\CheckSession;
use Illuminate\Support\Facades\Log;
use App\Services\KeyManagementService;
use App\Services\VerificationExperimentLogger;

class OtpController extends Controller
{
    protected $keyManagementService;
    protected $experimentLogger;

    public function __construct(KeyManagementService $keyManagementService, VerificationExperimentLogger $experimentLogger)
    {
        $this->keyManagementService = $keyManagementService;
        $this->experimentLogger = $experimentLogger;
    }

    public function sendOtp(Request $request)
    {
        // Validate email only for OTP resend
        if (session('registration_step') == 2) {
            $request->validate(['email' => 'required|email']);
        } else {
            // Validate full registration data on first request
            $request->validate([
                'username' => 'required|string|max:255',
                'password' => 'required|min:6',
                'phone' => 'required|string|digits_between:10,15',
                'email' => 'required|email'
            ]);
        }

        // Check if email already exists in User table
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            return back()->withErrors([
                'email' => 'This email is already registered in our system.'
            ])->withInput($request->except('password'));
        }
    
        // Generate OTP
        $otpCode = rand(100000, 999999);
        $expiresAt = Carbon::now()->addSeconds(180);
    
        Otp::updateOrCreate(
            ['email' => $request->email],
            ['otp_code' => $otpCode, 'expires_at' => $expiresAt]
        );
    
        // Only store registration data if it's the first OTP request
        if (session('registration_step') !== 2) {
            session([
                'registration_data' => [
                    'username' => $request->username,
                    'password' => bcrypt($request->password),
                    'raw_password' => $request->password, // Temporarily store for key derivation
                    'phone' => $request->phone,
                    'email' => $request->email
                ],
                'registration_step' => 2
            ]);
        }
    
        Mail::to($request->email)->send(new OtpMail($otpCode));
        return redirect()->route('register.otp.form');
    }
    
    public function showOtpForm()
    {
        // Check if session exists and is at step 2
        // if (!session()->has('registration_step') || session('registration_step') != 2) {
        //     return redirect()->route('register.check'); // Redirect to the correct step
        // }
    
        return view('auth.otp-form'); // Load OTP form only if step = 2
    }
    
    public function verifyOtp(Request $request)
    {
        $startedAt = microtime(true);

        $request->validate([
            'otp' => 'required|array',
            'otp.*' => 'digits:1'
        ]);
    
        $otpCode = implode('', $request->otp);
        $registrationData = session('registration_data', []);
    
        if (!isset($registrationData['email'], $registrationData['raw_password'])) {
            session()->flash('error', 'Session expired. Please request a new OTP.');
            $this->logOtpAttempt($request, false, 'session_expired', $startedAt);
            return back();
        }
    
        $email = $registrationData['email'];
    
        if (User::where('email', $email)->exists()) {
            session()->flash('error', 'This email is already registered.');
            $this->logOtpAttempt($request, false, 'email_already_registered', $startedAt);
            return redirect()->route('register.check');
        }
    
        $otpRecord = Otp::where('email', $email)->where('otp_code', $otpCode)->first();
    
        if (!$otpRecord) {
            session()->flash('error', 'Invalid OTP. Please try again.');
            $this->logOtpAttempt($request, false, 'invalid_otp', $startedAt);
            return back();
        }
    
        if (Carbon::now()->gt($otpRecord->expires_at)) {
            session()->flash('error', 'OTP has expired. Request a new one.');
            $this->logOtpAttempt($request, false, 'otp_expired', $startedAt);
            return back();
        }
    
        try {
            // 🔑 Call service to generate all key and certificate data
            $keyData = $this->keyManagementService->generateKeyDataAndCertificate($registrationData);
    
            unset($registrationData['raw_password']); // Clean up sensitive data
            $registrationData['key_data'] = $keyData;
    
            session([
                'registration_data' => $registrationData,
                'registration_step' => 3
            ]);
    
            $otpRecord->delete();
            session()->flash('success', 'OTP verified successfully.');
            $this->logOtpAttempt($request, true, null, $startedAt);
            return redirect()->route('ocr.file');
    
        } catch (\Exception $e) {
            Log::error('Key/Certificate generation error: ' . $e->getMessage());
            session()->flash('error', 'Failed to complete registration. Please try again.');
            $this->logOtpAttempt($request, false, 'key_generation_failed', $startedAt);
            return back();
        }
    }
    


    private function logOtpAttempt(Request $request, bool $passed, ?string $failureCause, float $startedAt): void
    {
        $isLegitimate = $request->input('is_legitimate');
        $isLegitimate = $isLegitimate === null ? null : filter_var($isLegitimate, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $this->experimentLogger->log([
            'method' => 'otp',
            'scenario' => $request->input('scenario', 'normal'),
            'is_legitimate' => $isLegitimate,
            'verification_passed' => $passed,
            'completion_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'failure_cause' => $failureCause,
            'metadata' => [
                'registration_step' => session('registration_step'),
            ],
        ]);
    }
}
