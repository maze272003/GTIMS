// SPA for navigation - Single Page Application 

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Kunin ang mga importanteng elements
    const mainContent = document.getElementById('main-content');
    const navLinks = document.querySelectorAll('.nav-link');

    // ---
    // 2. Function para mag-load ng bagong page
    // ---
    const loadPage = async (url, pushState = true) => {
        
        // Ipakita ang SKELETON LOADER
        if (mainContent) {
            mainContent.innerHTML = `
            <div class="pt-20 p-4 lg:p-8 min-h-screen">
                
                <div class="mb-6 pt-16">
                    <div class="h-4 bg-gray-200 rounded-md w-1/3 animate-pulse"></div>
                </div>
        
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                        <div class="flex items-center justify-between animate-pulse">
                            <div>
                                <div class="h-4 bg-gray-200 rounded-md w-24 mb-3"></div>
                                <div class="h-8 bg-gray-300 rounded-md w-16"></div>
                            </div>
                            <div class="w-14 h-14 bg-gray-200 rounded-full"></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                        <div class="flex items-center justify-between animate-pulse">
                            <div>
                                <div class="h-4 bg-gray-200 rounded-md w-24 mb-3"></div>
                                <div class="h-8 bg-gray-300 rounded-md w-16"></div>
                            </div>
                            <div class="w-14 h-14 bg-gray-200 rounded-full"></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                        <div class="flex items-center justify-between animate-pulse">
                            <div>
                                <div class="h-4 bg-gray-200 rounded-md w-24 mb-3"></div>
                                <div class="h-8 bg-gray-300 rounded-md w-16"></div>
                            </div>
                            <div class="w-14 h-14 bg-gray-200 rounded-full"></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                        <div class="flex items-center justify-between animate-pulse">
                            <div>
                                <div class="h-4 bg-gray-200 rounded-md w-24 mb-3"></div>
                                <div class="h-8 bg-gray-300 rounded-md w-16"></div>
                            </div>
                            <div class="w-14 h-14 bg-gray-200 rounded-full"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex flex-col sm:flex-row gap-3 w-full justify-end">
                    <div class="h-10 w-full sm:w-48 bg-gray-200 rounded-lg animate-pulse"></div>
                    <div class="h-10 w-full sm:w-48 bg-gray-200 rounded-lg animate-pulse"></div>
                </div>
        
                <div class="mt-5 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                        <div class="h-10 w-1/2 bg-gray-200 rounded-lg animate-pulse"></div>
                        <div class="h-10 w-36 bg-gray-200 rounded-lg animate-pulse"></div>
                    </div>
                    <div class="overflow-x-auto p-5">
                        <div class="w-full space-y-4">
                            <div class="flex gap-4">
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-1/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-2/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-4/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-1/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-2/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-2/12"></div>
                            </div>
                            <div class="flex gap-4">
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-1/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-2/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-4/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-1/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-2/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-2/12"></div>
                            </div>
                            <div class="flex gap-4">
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-1/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-2/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-4/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-1/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-2/12"></div>
                                <div class="h-8 bg-gray-200 rounded-md animate-pulse w-2/12"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;
        }
        
        // I-set ang active link sa sidebar
        setActiveLink(url);

        try {
            // ================== ITO ANG DINAGDAG NATIN ==================
            // 1. Simulan ang pag-fetch ng data
            const fetchPromise = fetch(url);
            
            // 2. Simulan ang 5-second (5000ms) timer
            const timeoutPromise = new Promise(resolve => setTimeout(resolve, 1500));
            
            // 3. Hintayin na matapos ang pareho (ang naunang matapos ay maghihintay)
            //    Ang 'response' ay galing sa fetchPromise
            const [response] = await Promise.all([fetchPromise, timeoutPromise]);
            // ==========================================================

            // (Ito 'yung dati nang code)
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const newContent = doc.getElementById('main-content').innerHTML;
            const newTitle = doc.querySelector('title').innerText;

            // Palitan ang content at title ng kasalukuyang page
            if (mainContent) {
                mainContent.innerHTML = newContent;
            }
            document.title = newTitle;

            // I-update ang URL sa browser
            if (pushState) {
                history.pushState({ path: url }, newTitle, url);
            }

        } catch (error) {
            console.error('Failed to load page:', error);
            window.location.href = url;
        }
    };

    // ---
    // 3. Function para i-set ang "Active" link (Gamit ang RED classes)
    // ---
    const setActiveLink = (url) => {
        
        let targetPathname;
        try {
            targetPathname = new URL(url).pathname;
        } catch (e) {
            console.error('Invalid URL:', url);
            targetPathname = '/';
        }

        navLinks.forEach(link => {
            let linkPathname;
            try {
                linkPathname = new URL(link.href).pathname;
            } catch (e) {
                linkPathname = link.getAttribute('href');
            }

            const icon = link.querySelector('i');
            const span = link.querySelector('span');

            if (linkPathname === targetPathname) {
                // Set as ACTIVE
                link.classList.add('bg-red-50', 'text-red-600');
                link.classList.remove('hover:bg-gray-50', 'text-gray-700', 'md:text-gray-700');
                if (icon) icon.classList.add('text-red-600');
                if (span) {
                    span.classList.add('text-red-600');
                    span.classList.remove('text-gray-700');
                }
            } else {
                // Set as INACTIVE
                link.classList.remove('bg-red-50', 'text-red-600');
                link.classList.add('hover:bg-gray-50', 'text-gray-700', 'md:text-gray-700');
                if (icon) icon.classList.remove('text-red-600');
                if (span) {
                    span.classList.remove('text-red-600');
                    span.classList.add('text-gray-700');
                }
            }
        });
    };

    // ---
    // 4. Idagdag ang Click Listener sa LAHAT ng nav-link
    // ---
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            if (link.href === window.location.href) {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            loadPage(link.href, true);
        });
    });

    // ---
    // 5. Handle ang Back/Forward buttons ng browser
    // ---
    window.addEventListener('popstate', () => {
        loadPage(location.href, false);
    });
    
    // ---
    // 6. I-set ang tamang active link sa unang pag-load ng page
    // ---
    setActiveLink(window.location.href);

});