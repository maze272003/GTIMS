<x-app-layout>
<body class="bg-gray-50 dark:bg-gray-900 antialiased selection:bg-blue-500 selection:text-white">
    <x-admin.sidebar/>

    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20 min-h-screen flex flex-col">
        <x-admin.header/>
        
        <main id="main-content" class="flex-1 pt-24 px-4 lg:px-8 pb-8">

            <div id="toast-container" class="fixed top-24 right-5 z-50 flex flex-col gap-3 pointer-events-none"></div>

            @if(session('success'))
                <script>document.addEventListener('DOMContentLoaded', () => showToast("{{ session('success') }}", 'success'));</script>
            @endif
            @if($errors->any())
                <script>document.addEventListener('DOMContentLoaded', () => showToast("Please check the form for errors.", 'error'));</script>
            @endif

            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="{{ route('admin.dashboard') }}" class="text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-white text-sm font-medium transition-colors">
                            <i class="fa-solid fa-home mr-2"></i>Dashboard
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fa-solid fa-chevron-right text-gray-400 mx-2 text-xs"></i>
                            <span class="text-blue-600 dark:text-blue-400 text-sm font-medium">Manage Accounts</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <div class="flex flex-col md:flex-row justify-between items-end md:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100">User Management</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 max-w-xl">
                        Create, update, and manage system access for all users across branches.
                    </p>
                </div>
                
                <button onclick="openUserModal('add')" class="group relative w-full md:w-auto bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-blue-600/30 hover:shadow-blue-600/50 transition-all duration-300 transform hover:-translate-y-0.5 flex items-center justify-center cursor-pointer">
                    <i class="fa-solid fa-plus mr-2 transition-transform group-hover:rotate-90"></i> 
                    <span>Add New User</span>
                </button>
            </div>

            <div class="mb-6">
                <div class="relative w-full md:w-96 group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-search text-gray-400 group-focus-within:text-blue-500 transition-colors"></i>
                    </div>
                    <input type="text" id="searchInput" class="block w-full pl-10 pr-10 py-3 border border-gray-200 dark:border-gray-700 rounded-xl leading-5 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 shadow-sm transition-all duration-200" placeholder="Search by name, email, or role...">
                    <div id="search-spinner" class="absolute inset-y-0 right-0 pr-3 flex items-center hidden">
                        <i class="fa-solid fa-circle-notch fa-spin text-blue-500"></i>
                    </div>
                </div>
            </div>

            <div id="table-container" class="min-h-[400px] transition-all duration-300 relative">
                @include('admin.partials.users-table')
            </div>

        </main>
    </div>

    <div id="userModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop"></div>

        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" id="modalPanel">
                    
                    <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white" id="modalTitle">Create Account</h3>
                        <button type="button" onclick="closeUserModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none transition-colors cursor-pointer">
                            <i class="fa-solid fa-xmark fa-lg"></i>
                        </button>
                    </div>

                    <form id="userForm" method="POST" action="{{ route('admin.manageaccount.store') }}">
                        @csrf
                        <div id="methodField"></div>

                        <div class="px-6 py-6 space-y-5">
                            <div class="group">
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Full Name</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-regular fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" name="name" id="inputName" required class="pl-10 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-shadow p-2.5">
                                </div>
                                @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            
                            <div class="group">
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Email Address</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-regular fa-envelope text-gray-400"></i>
                                    </div>
                                    <input type="email" name="email" id="inputEmail" required class="pl-10 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-shadow p-2.5">
                                </div>
                                @error('email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Role Access</label>
                                    <select name="user_level_id" id="inputRole" required class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm p-2.5">
                                        <option value="" disabled selected>Select Role</option>
                                        @foreach($levels as $level)
                                            <option value="{{ $level->id }}">{{ ucfirst($level->name) }}</option>
                                        @endforeach
                                    </select>
                                    @error('user_level_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Assigned Branch</label>
                                    @if(Auth::user()->level->name === 'superadmin')
                                        <select name="branch_id" id="inputBranch" required class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm p-2.5">
                                            <option value="" disabled selected>Select Branch</option>
                                            @foreach($branches as $branch)
                                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" value="{{ Auth::user()->branch->name }}" disabled class="block w-full rounded-lg border-gray-200 bg-gray-100 text-gray-500 sm:text-sm p-2.5 cursor-not-allowed">
                                        <input type="hidden" name="branch_id" value="{{ Auth::user()->branch_id }}">
                                    @endif
                                </div>
                            </div>
                            
                            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex justify-between items-center mb-1.5">
                                    <label id="passwordLabel" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Password</label>
                                    <button type="button" onclick="generateStrongPassword()" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium hover:underline focus:outline-none transition-colors cursor-pointer">
                                        <i class="fa-solid fa-wand-magic-sparkles mr-1"></i>Auto-Generate
                                    </button>
                                </div>

                                <div class="relative group">
                                    <input type="password" name="password" id="inputPassword" oninput="checkPasswordStrength(this.value)" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500 sm:text-sm pr-10 p-2.5 transition-all" placeholder="••••••••">
                                    <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer focus:outline-none">
                                        <i id="eyeIcon" class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                
                                <div class="mt-3 grid grid-cols-3 gap-2">
                                    <div id="bar-len" class="h-1.5 w-full bg-gray-200 dark:bg-gray-600 rounded-full transition-colors duration-300"></div>
                                    <div id="bar-num" class="h-1.5 w-full bg-gray-200 dark:bg-gray-600 rounded-full transition-colors duration-300"></div>
                                    <div id="bar-sym" class="h-1.5 w-full bg-gray-200 dark:bg-gray-600 rounded-full transition-colors duration-300"></div>
                                </div>
                                <div class="mt-2 flex justify-between text-[10px] text-gray-400 font-medium uppercase tracking-wider">
                                    <span id="txt-len">8+ Chars</span>
                                    <span id="txt-num">Number</span>
                                    <span id="txt-sym">Symbol</span>
                                </div>
                                @error('password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 flex flex-row-reverse gap-3 rounded-b-2xl">
                            <button type="button" id="adduserbtn" class="inline-flex w-full justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:ml-3 sm:w-auto transition-colors cursor-pointer">Save User</button>
                            <button type="button" onclick="closeUserModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white dark:bg-gray-600 px-4 py-2.5 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors cursor-pointer">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const tableContainer = document.getElementById('table-container');
            const searchSpinner = document.getElementById('search-spinner');
            let searchTimer;

            searchInput.addEventListener('keyup', function () {
                clearTimeout(searchTimer);
                searchSpinner.classList.remove('hidden'); 
                searchTimer = setTimeout(() => {
                    fetchUsers(1, this.value);
                }, 400);
            });

            tableContainer.addEventListener('click', function (e) {
                const link = e.target.closest('a');
                if (link && link.href && link.href.includes('page=')) {
                    e.preventDefault();
                    const url = new URL(link.href);
                    fetchUsers(url.searchParams.get('page'), searchInput.value);
                }
            });

            function fetchUsers(page, query) {
                const loader = document.getElementById('table-loader');
                if(loader) {
                    loader.classList.remove('hidden');
                    loader.classList.add('flex');
                }

                let url = `{{ route('admin.manageaccount') }}?page=${page}&search=${query}`;

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => {
                    tableContainer.innerHTML = html;
                    searchSpinner.classList.add('hidden');
                })
                .catch(err => {
                    console.error(err);
                    searchSpinner.classList.add('hidden');
                    showToast("Failed to load data.", 'error');
                });
            }
        });

        function checkPasswordStrength(password) {
            const hasLength = password.length >= 8;
            const hasNumber = /[0-9]/.test(password);
            const hasSymbol = /[@$!%*#?&]/.test(password);

            const updateBar = (barId, txtId, valid) => {
                const bar = document.getElementById(barId);
                const txt = document.getElementById(txtId);
                if (valid) {
                    bar.classList.remove('bg-gray-200', 'dark:bg-gray-600');
                    bar.classList.add('bg-green-500');
                    txt.classList.add('text-green-600', 'font-bold', 'dark:text-green-400');
                } else {
                    bar.classList.add('bg-gray-200', 'dark:bg-gray-600');
                    bar.classList.remove('bg-green-500');
                    txt.classList.remove('text-green-600', 'font-bold', 'dark:text-green-400');
                }
            };

            updateBar('bar-len', 'txt-len', hasLength);
            updateBar('bar-num', 'txt-num', hasNumber);
            updateBar('bar-sym', 'txt-sym', hasSymbol);

            const input = document.getElementById('inputPassword');
            if (hasLength && hasNumber && hasSymbol) {
                input.classList.add('border-green-500', 'focus:border-green-500', 'focus:ring-green-500');
                input.classList.remove('border-gray-300');
            } else {
                input.classList.remove('border-green-500', 'focus:border-green-500', 'focus:ring-green-500');
                input.classList.add('border-gray-300');
            }
        }

        function openUserModal(mode, user = null) {
            const modal = document.getElementById('userModal');
            const backdrop = document.getElementById('modalBackdrop');
            const panel = document.getElementById('modalPanel');
            const form = document.getElementById('userForm');
            const methodField = document.getElementById('methodField');

            document.getElementById('inputName').value = '';
            document.getElementById('inputEmail').value = '';
            document.getElementById('inputRole').value = '';
            document.getElementById('inputPassword').value = '';
            document.getElementById('passwordLabel').innerText = 'Password';
            checkPasswordStrength('');

            if (mode === 'edit' && user) {
                document.getElementById('modalTitle').innerText = 'Edit User Account';
                form.action = `/admin/manageaccount/${user.id}`;
                methodField.innerHTML = '<input type="hidden" name="_method" value="PUT">';
                document.getElementById('inputName').value = user.name;
                document.getElementById('inputEmail').value = user.email;
                document.getElementById('inputRole').value = user.user_level_id;
                if(document.getElementById('inputBranch')) document.getElementById('inputBranch').value = user.branch_id;
                document.getElementById('passwordLabel').innerText = 'New Password (Optional)';
            } else {
                document.getElementById('modalTitle').innerText = 'Create Account';
                form.action = "{{ route('admin.manageaccount.store') }}";
                methodField.innerHTML = '';
                if(document.getElementById('inputBranch')) document.getElementById('inputBranch').value = '';
            }

            modal.classList.remove('hidden');
            void modal.offsetWidth; 
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'translate-y-4', 'sm:scale-95');
            panel.classList.add('opacity-100', 'translate-y-0', 'sm:scale-100');
        }

        function closeUserModal() {
            const modal = document.getElementById('userModal');
            const backdrop = document.getElementById('modalBackdrop');
            const panel = document.getElementById('modalPanel');

            backdrop.classList.add('opacity-0');
            panel.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100');
            panel.classList.add('opacity-0', 'translate-y-4', 'sm:scale-95');

            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target.id === 'modalBackdrop') closeUserModal();
        });

        function togglePasswordVisibility() {
            const input = document.getElementById('inputPassword');
            const icon = document.getElementById('eyeIcon');
            if(input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye');
            }
        }

        function generateStrongPassword() {
            const length = 12;
            const numbers = "0123456789";
            const symbols = "@$!%*#?&";
            const letters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
            
            let pass = "";
            pass += numbers.charAt(Math.floor(Math.random() * numbers.length));
            pass += symbols.charAt(Math.floor(Math.random() * symbols.length));
            pass += letters.charAt(Math.floor(Math.random() * letters.length));
            
            const allChars = letters + numbers + symbols;
            for (let i = 3; i < length; i++) {
                 const array = new Uint32Array(1);
                 window.crypto.getRandomValues(array);
                 pass += allChars[array[0] % allChars.length];
            }
            
            pass = pass.split('').sort(() => 0.5 - Math.random()).join('');
            
            const input = document.getElementById('inputPassword');
            input.value = pass;
            input.type = 'text';
            document.getElementById('eyeIcon').classList.remove('fa-eye');
            document.getElementById('eyeIcon').classList.add('fa-eye-slash');
            checkPasswordStrength(pass);
        }

        function showToast(msg, type) {
            const container = document.getElementById('toast-container');
            const div = document.createElement('div');
            const borderColor = type === 'success' ? 'border-green-500' : 'border-red-500';
            const iconClass = type === 'success' ? 'fa-circle-check text-green-500' : 'fa-circle-exclamation text-red-500';
            
            div.className = `bg-white dark:bg-gray-800 shadow-xl rounded-lg p-4 border-l-4 ${borderColor} pointer-events-auto flex items-center gap-3 transform transition-all duration-300 translate-x-full opacity-0 mb-3 w-80`;
            
            div.innerHTML = `
                <i class="fa-solid ${iconClass} text-xl"></i>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">${msg}</p>
            `;
            
            container.appendChild(div);

            requestAnimationFrame(() => {
                div.classList.remove('translate-x-full', 'opacity-0');
            });

            setTimeout(() => {
                div.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => div.remove(), 300);
            }, 4000);
        }

        document.getElementById('adduserbtn').addEventListener('click', function() {
            const form = document.getElementById('userForm');
            const inputs = form.querySelectorAll('input[type="text"], input[type="number"], input[type="date"] , input[type="email"], select[name="user_level_id"], select[name="branch_id"]');
            let allFilled = true;

            inputs.forEach(input => {
                if (input.value.trim() === '') {
                allFilled = false;
                }
            });

            if (!allFilled) {
                Swal.fire({
                title: 'Incomplete Form',
                text: 'Please fill in all required fields before submitting.',
                icon: 'warning',
                confirmButtonText: 'OK',
                allowOutsideClick: false,
                customClass: {
                    container: 'swal-container',
                    popup: 'swal-popup',
                    title: 'swal-title',
                    htmlContainer: 'swal-content',
                    confirmButton: 'swal-confirm-button',
                    icon: 'swal-icon'
                }
                });
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: "Please confirm if you want to proceed.",
                icon: 'info',
                showCancelButton: true,
                cancelButtonText: 'Cancel',
                confirmButtonText: 'Confirm',
                allowOutsideClick: false,
                customClass: {
                container: 'swal-container',
                popup: 'swal-popup',
                title: 'swal-title',
                htmlContainer: 'swal-content',
                confirmButton: 'swal-confirm-button',
                cancelButton: 'swal-cancel-button',
                icon: 'swal-icon'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                Swal.fire({
                    title: 'Processing...',
                    text: "Please wait, your request is being processed.",
                    allowOutsideClick: false,
                    customClass: {
                    container: 'swal-container',
                    popup: 'swal-popup',
                    title: 'swal-title',
                    htmlContainer: 'swal-content',
                    cancelButton: 'swal-cancel-button',
                    icon: 'swal-icon'
                    },
                    didOpen: () => {
                    Swal.showLoading();
                    }
                });
                form.submit();
                }
            });
            });
    </script>
</body>
</x-app-layout>