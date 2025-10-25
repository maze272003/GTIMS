<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bawal Dito!</title>
    <script src="https://cdn.tailwindcss.com"></script> {{-- Or use your app's @vite CSS --}}
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="text-center p-10 bg-white rounded-lg shadow-xl">
        <h1 class="text-9xl font-bold text-red-500">403</h1>
        <h2 class="text-3xl font-semibold text-gray-800 mt-4">Oops! Bawal ka dito.</h2>
        <p class="text-gray-600 mt-2">Paumanhin, mukhang wala kang permiso para buksan ang page na ito.</p>
        <div class="mt-8">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-md font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Bumalik sa Home at Mag-logout
                </button>
            </form>
        </div>
    </div>
</body>
</html>