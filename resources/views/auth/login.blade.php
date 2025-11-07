<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>General Tinio - Inventory System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="{{ asset('images/gtlogo.png') }}">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
</head>
<body class="bg-gradient-to-r from-blue-100 to-blue-300 flex items-center justify-center min-h-screen p-4">
    <div id="logincontainer" class="flex flex-col lg:flex-row rounded-lg bg-white w-full max-w-4xl overflow-hidden shadow-2xl">
        <div class="flex flex-col gap-4 w-full lg:w-1/2 p-8 md:p-12">
            <div class="flex flex-col items-center gap-2 text-custom-teal-800">
                <img src="{{asset('images/gtlogo.png')}}" alt="logo" class="w-16">
                <h1 class="text-xl font-semibold">Municipality of General Tinio</h1>
            </div>
            <h1 class="text-center mt-2 font-medium tracking-wide text-xl md:text-2xl">Sign in to your Account</h1>
            <h2 class="text-sm md:text-base text-center text-blue-500 font-medium">General Tinio RHU - Inventory Management System</h2>
            
            <div id="ajax-error-message" class="text-red-500 text-center text-sm font-medium hidden"></div>
            <div id="ajax-success-message" class="text-green-500 text-center text-sm font-medium hidden"></div>

            @if (session('status'))
                <p class="text-green-500 text-center text-sm">{{ session('status') }}</p>
            @endif
            @if (session('error'))
                <div class="text-red-500 text-center text-sm font-medium">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-2 space-y-6" id="password-form">
                @csrf
                <div>
                    <label for="email" class="text-sm text-black/80 font-medium">Email Address:</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" 
                           class="border border-gray-300 bg-white w-full p-3 rounded-lg outline-none mt-2 text-sm focus:border-custom-teal-800 focus:ring-2 focus:ring-custom-teal-800/50 transition-all duration-200" 
                           placeholder="Enter Your Email" required autofocus autocomplete="username">
                    @error('email')
                        <p class="text-red-500 text-sm mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <div class="flex justify-between items-center">
                        <label for="password" class="text-sm text-black/80 font-medium">Password:</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm text-custom-teal-800 font-semibold hover:underline">Forgot Password?</a>
                        @endif
                    </div>
                    <div class="relative">
                        <input id="password" type="password" name="password" 
                               class="border border-gray-300 bg-white w-full p-3 rounded-lg outline-none mt-2 text-sm focus:border-custom-teal-800 focus:ring-2 focus:ring-custom-teal-800/50 transition-all duration-200" 
                               placeholder="Enter Your Password" required autocomplete="current-password">
                        <button type="button" onclick="showpassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-custom-teal-800 transition-colors duration-200">
                            <i id="eye" class="fa-solid fa-eye mt-2"></i>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-red-500 text-sm mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center gap-2">
                    <input id="remember_me" type="checkbox" name="remember" 
                           class="w-4 h-4 text-custom-teal-800 focus:ring-custom-teal-800 border-gray-300 rounded">
                    <label for="remember_me" class="text-sm text-black/80 font-medium">Remember Me</label>
                </div>
                <button type="submit" id="loginButton"
                        class="bg-blue-700 w-full p-3 rounded-lg text-white font-medium text-sm md:text-base hover:bg-blue-800 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                    Log in
                </button>
            </form>

            <div id="otp-form" class="mt-2 space-y-6 hidden">
                <div id="otp-input-container" class="hidden">
                    <label for="otp" class="text-sm text-black/80 font-medium">Enter OTP:</label>
                    <input id="otp" type="text" name="otp" 
                           class="border border-gray-300 bg-white w-full p-3 rounded-lg outline-none mt-2 text-sm focus:border-custom-teal-800 focus:ring-2 focus:ring-custom-teal-800/50 transition-all duration-200" 
                           placeholder="Enter 6-digit OTP" maxlength="6">
                </div>
                
                <button type="button" id="send-otp-button"
                        class="bg-green-600 w-full p-3 rounded-lg text-white font-medium text-sm md:text-base hover:bg-green-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                    Send OTP to Email
                </button>
                <button type="button" id="verify-otp-button"
                        class="bg-blue-700 w-full p-3 rounded-lg text-white font-medium text-sm md:text-base hover:bg-blue-800 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 hidden">
                    Verify OTP & Log in
                </button>
            </div>

            <div class="text-center mt-4">
                <a href="#" id="toggle-to-otp" class="text-sm text-custom-teal-800 font-semibold hover:underline">
                    Login with OTP instead
                </a>
                <a href="#" id="toggle-to-password" class="text-sm text-custom-teal-800 font-semibold hover:underline hidden">
                    Login with Password instead
                </a>
            </div>

        </div>
        <div id="flip" class="hidden lg:flex w-1/2 relative bg-custom-teal-900 p-12 flex-col items-center justify-center text-center overflow-hidden">
            <img src="{{asset('images/Gtcover.jpg')}}" alt="login" class="w-full h-full object-cover absolute top-0 left-0 inset-0 z-10">
        </div>
    </div>

    <script>
        // Luma mong script para sa show password
        function showpassword() {
            var password = document.getElementById('password');
            var eye = document.getElementById('eye');
            if (password.type === 'password') {
                password.type = 'text';
                eye.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                password.type = 'password';
                eye.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    
        // Bagong script para sa OTP, Toggle, at Resend Timer
        document.addEventListener('DOMContentLoaded', () => {
            // Mga Elemento
            const emailInput = document.getElementById('email');
            const passwordForm = document.getElementById('password-form');
            const otpForm = document.getElementById('otp-form');
            const otpInputContainer = document.getElementById('otp-input-container');
            const otpInput = document.getElementById('otp');
            
            const sendOtpButton = document.getElementById('send-otp-button');
            const verifyOtpButton = document.getElementById('verify-otp-button');
            
            const toggleToOtp = document.getElementById('toggle-to-otp');
            const toggleToPassword = document.getElementById('toggle-to-password');
            
            const successMsg = document.getElementById('ajax-success-message');
            const errorMsg = document.getElementById('ajax-error-message');

            // Para sa Timer
            let otpTimerInterval = null;
            const resendCooldown = 60; // 60 segundo (1 minuto)

            // Function para magpalit ng mode
            function toggleMode(mode) {
                successMsg.classList.add('hidden');
                errorMsg.classList.add('hidden');
                
                if (mode === 'otp') {
                    passwordForm.classList.add('hidden');
                    toggleToOtp.classList.add('hidden');
                    
                    otpForm.classList.remove('hidden');
                    toggleToPassword.classList.remove('hidden');
                    
                    // Check agad ang timer state paglipat
                    handleTimerTick(); 

                } else { // 'password' mode
                    passwordForm.classList.remove('hidden');
                    toggleToOtp.classList.remove('hidden');
                    
                    otpForm.classList.add('hidden');
                    toggleToPassword.classList.add('hidden');
                }
            }

            // --- START: Logic para sa Resend Timer ---
            
            function startResendCooldown() {
                const endTime = Date.now() + resendCooldown * 1000;
                localStorage.setItem('otpCooldownEnd', endTime);

                // Ipakita agad ang OTP input
                otpInputContainer.classList.remove('hidden');
                verifyOtpButton.classList.remove('hidden');
                otpInput.focus();

                handleTimerTick(); // Patakbuhin agad

                if (otpTimerInterval) clearInterval(otpTimerInterval); // Linisin ang lumang interval
                otpTimerInterval = setInterval(handleTimerTick, 1000);
            }

            function handleTimerTick() {
                const endTime = localStorage.getItem('otpCooldownEnd');
                if (!endTime) {
                    enableResendButton();
                    return;
                }

                const remainingMs = endTime - Date.now();

                if (remainingMs <= 0) {
                    localStorage.removeItem('otpCooldownEnd');
                    if (otpTimerInterval) clearInterval(otpTimerInterval);
                    enableResendButton();
                } else {
                    disableResendButton(remainingMs);
                }
            }

            function disableResendButton(remainingMs) {
                const remainingSeconds = Math.ceil(remainingMs / 1000);
                sendOtpButton.disabled = true;
                sendOtpButton.innerText = `Resend OTP in ${remainingSeconds}s`;
                sendOtpButton.classList.add('opacity-50', 'cursor-not-allowed');
                sendOtpButton.classList.remove('hover:bg-green-700');
            }

            function enableResendButton() {
                sendOtpButton.disabled = false;
                
                // Alamin kung "Send" o "Resend" ang text
                if (otpInputContainer.classList.contains('hidden')) {
                    sendOtpButton.innerText = 'Send OTP to Email';
                } else {
                    sendOtpButton.innerText = 'Resend OTP';
                }
                
                sendOtpButton.classList.remove('opacity-50', 'cursor-not-allowed');
                sendOtpButton.classList.add('hover:bg-green-700');
            }

            // --- END: Logic para sa Resend Timer ---


            // Event Listeners para sa Toggles
            toggleToOtp.addEventListener('click', (e) => {
                e.preventDefault();
                toggleMode('otp');
            });
            
            toggleToPassword.addEventListener('click', (e) => {
                e.preventDefault();
                toggleMode('password');
            });

            // Event Listener para sa "Send/Resend OTP"
            sendOtpButton.addEventListener('click', async () => {
                const email = emailInput.value;
                if (!email) {
                    errorMsg.innerText = 'Please enter your email address first.';
                    errorMsg.classList.remove('hidden');
                    return;
                }

                // I-disable agad habang nagpapadala
                sendOtpButton.disabled = true;
                sendOtpButton.innerText = 'Sending...';
                errorMsg.classList.add('hidden');
                successMsg.classList.add('hidden');

                try {
                    const response = await fetch('{{ route("otp.send") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ email: email })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        enableResendButton(); // I-enable ulit kung nagka-error
                        throw new Error(data.message || 'An error occurred.');
                    }

                    // Success!
                    successMsg.innerText = data.message;
                    successMsg.classList.remove('hidden');
                    
                    // HUWAG MUNA I-ENABLE. Hayaan ang timer ang mag-manage.
                    // Simulan ang cooldown timer
                    startResendCooldown();

                } catch (error) {
                    errorMsg.innerText = error.message;
                    errorMsg.classList.remove('hidden');
                    enableResendButton(); // I-enable ulit kung nagka-error
                }
            });

            // Event Listener para sa "Verify OTP"
            verifyOtpButton.addEventListener('click', async () => {
                const email = emailInput.value;
                const otp = otpInput.value;

                if (!otp || otp.length !== 6) {
                    errorMsg.innerText = 'Please enter a valid 6-digit OTP.';
                    errorMsg.classList.remove('hidden');
                    return;
                }

                verifyOtpButton.disabled = true;
                verifyOtpButton.innerText = 'Verifying...';
                errorMsg.classList.add('hidden');
                successMsg.classList.add('hidden');

                try {
                    const response = await fetch('{{ route("otp.verify") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ email: email, otp: otp })
                    });
                    
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'An error occurred.');
                    }

                    // Success! Redirect
                    successMsg.innerText = 'Login successful! Redirecting...';
                    successMsg.classList.remove('hidden');
                    
                    // Alisin ang timer sa localStorage para di na tumakbo
                    localStorage.removeItem('otpCooldownEnd');
                    if (otpTimerInterval) clearInterval(otpTimerInterval);

                    // Redirect to dashboard
                    window.location.href = data.redirect_url;

                } catch (error) {
                    errorMsg.innerText = error.message;
                    errorMsg.classList.remove('hidden');
                    verifyOtpButton.disabled = false;
                    verifyOtpButton.innerText = 'Verify OTP & Log in';
                }
            });
            
            // --- Patakbuhin on Page Load ---
            // I-check kung may active cooldown timer sa localStorage
            handleTimerTick(); // Patakbuhin agad on load
            if (localStorage.getItem('otpCooldownEnd')) {
                if (otpTimerInterval) clearInterval(otpTimerInterval);
                otpTimerInterval = setInterval(handleTimerTick, 1000);
            }
            
            // (Isama mo dito 'yung luma mong lockout logic galing sa login.js kung kailangan)
        });
    </script>
</body>
</html>