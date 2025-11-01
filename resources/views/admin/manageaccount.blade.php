<x-app-layout>
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body, html, input, button, select, textarea {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100">
    <x-slot name="title">
        Management Account - General Tinio
    </x-slot>
    <x-admin.sidebar/>

    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
            
            <div class="mb-6 pt-16">
                <p class="text-sm text-gray-500">Home / <span class="text-gray-900 font-medium">Manage Account</span></p>
            </div>

            <div x-data="userManagement()">

                <div class="flex flex-col md:flex-row justify-between items-start mb-6 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            User Accounts
                        </h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Manage all Superadmin, Admin, and Encoder accounts.
                        </p>
                    </div>
                    <button 
                        @click="openModal('add')"
                        class="w-full sm:w-auto bg-blue-600 text-white font-medium py-2.5 px-5 rounded-lg shadow-md hover:bg-blue-700 transition duration-300 flex items-center justify-center">
                        <i class="fa-solid fa-plus mr-2"></i> Add New User
                    </button>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center mb-5 gap-4">
                    <div class="relative w-full md:w-1/2 lg:w-1/3">
                        <input type="text" 
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" 
                               placeholder="Search by name or email...">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-search text-gray-400"></i>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row w-full md:w-auto items-center gap-4">
                        <select class="w-full sm:w-auto bg-white border border-gray-300 rounded-lg py-2.5 px-4 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                            <option value="">All Roles</option>
                            <option value="superadmin">Superadmin</option>
                            <option value="admin">Admin</option>
                            <option value="encoder">Encoder</option>
                        </select>
                    </div>
                </div>


                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-white border-b-2 border-gray-200">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date Added</th>
                                    <th scope="col" class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full object-cover" src="https://ui-avatars.com/api/?name=Super+Admin&background=4F46E5&color=fff&bold=true" alt="Super Admin">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">Super Admin</div>
                                                <div class="text-sm text-gray-500">super@rhu.gov.ph</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                            <i class="fa-solid fa-shield-halved mr-1.5"></i> Superadmin
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        Oct 20, 2025
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <button @click="openModal('edit', { id: 1, name: 'Super Admin', email: 'super@rhu.gov.ph', role: 'superadmin' })" class="text-gray-400 hover:text-blue-600 transition duration-150 mx-2" title="Edit">
                                            <i class="fa-solid fa-pencil fa-lg"></i>
                                        </button>
                                        <button class="text-gray-400 hover:text-red-600 transition duration-150 mx-2" title="Delete">
                                            <i class="fa-solid fa-trash-can fa-lg"></i>
                                        </button>
                                    </td>
                                </tr>

                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                 <img class="h-10 w-10 rounded-full object-cover" src="https://ui-avatars.com/api/?name=Juan+Dela+Cruz&background=0E7490&color=fff&bold=true" alt="Juan Dela Cruz">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">Juan Dela Cruz</div>
                                                <div class="text-sm text-gray-500">juan.admin@rhu.gov.ph</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-cyan-100 text-cyan-800">
                                            <i class="fa-solid fa-user-gear mr-1.5"></i> Admin
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        Sep 15, 2025
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <button @click="openModal('edit', { id: 2, name: 'Juan Dela Cruz', email: 'juan.admin@rhu.gov.ph', role: 'admin' })" class="text-gray-400 hover:text-blue-600 transition duration-150 mx-2" title="Edit">
                                            <i class="fa-solid fa-pencil fa-lg"></i>
                                        </button>
                                        <button class="text-gray-400 hover:text-red-600 transition duration-150 mx-2" title="Delete">
                                            <i class="fa-solid fa-trash-can fa-lg"></i>
                                        </button>
                                    </td>
                                </tr>

                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full object-cover" src="https://ui-avatars.com/api/?name=Maria+Santos&background=059669&color=fff&bold=true" alt="Maria Santos">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">Maria Santos</div>
                                                <div class="text-sm text-gray-500">maria.santos@rhu.gov.ph</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-teal-100 text-teal-800">
                                            <i class="fa-solid fa-keyboard mr-1.5"></i> Encoder
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        Oct 30, 2025
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <button @click="openModal('edit', { id: 3, name: 'Maria Santos', email: 'maria.santos@rhu.gov.ph', role: 'encoder' })" class="text-gray-400 hover:text-blue-600 transition duration-150 mx-2" title="Edit">
                                            <i class="fa-solid fa-pencil fa-lg"></i>
                                        </button>
                                        <button class="text-gray-400 hover:text-red-600 transition duration-150 mx-2" title="Delete">
                                            <i class="fa-solid fa-trash-can fa-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-5 bg-white border-t border-gray-200">
                        <nav class="flex justify-between items-center" aria-label="Pagination">
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium">1</span> to <span class="font-medium">3</span> of <span class="font-medium">3</span> results (Sample)
                            </p>
                            <div class="flex gap-2">
                                <a href="#" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-500 bg-white hover:bg-gray-50 transition-colors">
                                    <i class="fa-solid fa-chevron-left mr-2 -ml-1 h-5 w-5"></i>
                                    Previous
                                </a>
                                <a href="#" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    Next
                                    <i class="fa-solid fa-chevron-right ml-2 -mr-1 h-5 w-5"></i>
                                </a>
                            </div>
                        </nav>
                    </div>
                </div>
                
                <div 
                    x-show="isModalOpen" 
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 p-4"
                    @click.away="isModalOpen = false"
                    style="display: none;">
                    
                    <div 
                        x-show="isModalOpen"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="bg-white rounded-lg shadow-xl w-full max-w-lg" 
                        @click.stop>
                        
                        <div class="flex justify-between items-center p-6 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-900" x-text="modalTitle"></h2>
                            <button @click="isModalOpen = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fa-solid fa-times fa-lg"></i>
                            </button>
                        </div>
                        
                        <form @submit.prevent="saveUser">
                            <div class="p-6 space-y-5">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                    <input type="text" id="name" x-model="name"
                                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                    <input type="email" id="email" x-model="email"
                                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>

                                <div>
                                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <select id="role" x-model="role"
                                            class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                            required>
                                        <option value="encoder">Encoder</option> 
                                        <option value="admin">Admin</option>
                                        <option value="superadmin">Superadmin</option>
                                    </select>
                                </div>
                                
                                <div x-show="!isEditMode">
                                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                    <input type="password" id="password"
                                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Set a strong password">
                                </div>
                                <div x-show="isEditMode">
                                    <label for="password_new" class="block text-sm font-medium text-gray-700 mb-1">New Password (Optional)</label>
                                    <input type="password" id="password_new"
                                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Leave blank to keep current password">
                                </div>
                            </div>
                            
                            <div class="flex justify-end items-center p-6 bg-gray-50 border-t border-gray-200 rounded-b-lg space-x-3">
                                <button type="button" @click="isModalOpen = false"
                                        class="bg-white py-2 px-4 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                        class="bg-blue-600 py-2 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                    Save User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            
            <script>
                function userManagement() {
                    return {
                        isModalOpen: false,
                        isEditMode: false,
                        modalTitle: 'Add New User',
                        userId: null,
                        name: '',
                        email: '',
                        role: 'encoder',
                        
                        openModal(mode, user = null) {
                            if (mode === 'add') {
                                this.isEditMode = false;
                                this.modalTitle = 'Add New User';
                                this.userId = null;
                                this.name = '';
                                this.email = '';
                                this.role = 'encoder';
                            } else if (mode === 'edit' && user) {
                                this.isEditMode = true;
                                this.modalTitle = 'Edit User Account';
                                this.userId = user.id;
                                this.name = user.name;
                                this.email = user.email;
                                this.role = user.role;
                            }
                            this.isModalOpen = true;
                        },

                        saveUser() {
                            if (this.isEditMode) {
                                console.log('Updating user...', this.userId, this.name, this.email, this.role);
                            } else {
                                console.log('Adding new user...', this.name, this.email, this.role);
                            }
                            this.isModalOpen = false;
                        }
                    }
                }
            </script>
            
        </main>
    </div>

</body>
</x-app-layout>
