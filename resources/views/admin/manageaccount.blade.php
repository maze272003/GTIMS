<x-app-layout>
<body class="bg-gray-50">
    <x-slot name="title">
        Management Account - General Tinio
    </x-slot>
    <x-admin.sidebar/>

    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
            <div class="mb-6 pt-16">
                <p class="text-sm text-gray-600">Home / <span class="text-red-700 font-medium">Manage Account</span></p>
            </div>
        </main>
    </div>

</body>
</x-app-layout>