@extends('layouts.app')
    @section('header')
        Check Your Email
    @endsection
    @section('content')
        @if(session('error'))
            <div id="notif" class="my-4 p-3 text-sm text-black bg-red-300 rounded-sm text-center">
                {{ session('error') }}
            </div>
        @endif
                    
        <form id="otp-form" method="POST" action="/verify-otp">
            @csrf   
            <div class="font-bold my-4">OTP Has been Sent to <span class="text-blue-400">{{ session('registration_data')['email'] }}</span></div>
            @for ($i = 0; $i < 6; $i++)
                <input type="text" name="otp[]" maxlength="1" 
                    class="otp-input w-10 h-10 text-center border-2 rounded-sm focus:outline-none focus:ring-2 focus:ring-blue-400 text-xl font-semibold">
            @endfor
            <div class="my-5">
                <button class="w-full bg-black text-white py-2 rounded" type="submit">Verify OTP</button>
            </div>
        </form>
        <div class="text-center mt-4">
            <form id="resend-form" action="/send-otp" method="POST">
                <p>Didn't receive the OTP?</p>
                @csrf
                <input type="hidden" name="email" value="{{ session('registration_data')['email'] ?? '' }}">
                <button id="resend-btn" type="submit" class="text-blue-500 font-bold border-none bg-transparent cursor-pointer">
                    Resend OTP
                </button>

                <p id="timer" class="text-sm text-gray-500 mt-2 hidden">
                    Resend available in <span id="countdown">60</span>s
                </p>
            </form>
        </div>

        <script>
            const resendForm = document.getElementById('resend-form');
            const resendBtn = document.getElementById('resend-btn');
            const timer = document.getElementById('timer');
            const countdownEl = document.getElementById('countdown');

            let seconds = 60;
            let interval = null;

            resendForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                if(interval) return;

                await fetch(resendForm.action, {
                    method: 'POST',
                    body: new FormData(resendForm)
                });

                // Disable button
                resendBtn.disabled = true;
                resendBtn.classList.add('opacity-50', 'cursor-not-allowed');

                // Show timer
                timer.classList.remove('hidden');
                countdownEl.textContent = seconds;

                interval = setInterval(() => {
                    seconds--;
                    countdownEl.textContent = seconds;

                    if (seconds <= 0) {
                        clearInterval(interval);
                        resendBtn.disabled = false;
                        resendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        timer.classList.add('hidden');
                        seconds = 60;
                    }
                }, 1000);
            });
        </script>

    @endsection
        