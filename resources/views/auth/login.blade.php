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
            <form method="POST" action="{{ route('login') }}" class="mt-2 space-y-6">
                @csrf
                @if (session('status'))
                    <p class="text-green-500 text-center text-sm">{{ session('status') }}</p>
                @endif
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
            {{-- <div class="text-center mt-6 text-sm text-gray-600">
                Don't have an account? <a href="{{ route('register') }}" class="font-semibold text-custom-teal-800 hover:underline">Sign up</a>
            </div> --}}
        </div>
        <div id="flip" class="hidden lg:flex w-1/2 relative bg-custom-teal-900 p-12 flex-col items-center justify-center text-center overflow-hidden">
            <img src="{{asset('images/Gtcover.jpg')}}" alt="login" class="w-full h-full object-cover absolute top-0 left-0 inset-0 z-10">
        </div>
    </div>
</body>
</html>
<script src={{ asset('js/login.js') }}></script>