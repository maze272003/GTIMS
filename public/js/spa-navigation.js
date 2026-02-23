document.addEventListener('DOMContentLoaded', () => {
    
    const mainContent = document.getElementById('main-content');
    const navLinks = document.querySelectorAll('.nav-link');

    const loadPage = async (url, pushState = true) => {
        
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
        
        setActiveLink(url);

        try {
            const fetchPromise = fetch(url);
            const timeoutPromise = new Promise(resolve => setTimeout(resolve, 1500));
            const [response] = await Promise.all([fetchPromise, timeoutPromise]);

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const newContent = doc.getElementById('main-content').innerHTML;
            const newTitle = doc.querySelector('title').innerText;

            if (mainContent) {
                mainContent.innerHTML = newContent;
            }
            document.title = newTitle;

            if (pushState) {
                history.pushState({ path: url }, newTitle, url);
            }

            if (typeof initializeInventoryPage === 'function' && url.includes('/inventory')) {
                initializeInventoryPage();
            }

            const newScripts = doc.querySelectorAll('script');
            newScripts.forEach(script => {
                if (!script.src && (script.innerText.includes('errors.addproduct') || script.innerText.includes('errors.addstock'))) {
                    const newScript = document.createElement('script');
                    newScript.text = script.innerText;
                    document.body.appendChild(newScript).parentNode.removeChild(newScript);
                }
            });

        } catch (error) {
            if (typeof gtToast !== 'undefined') gtToast.error('Failed to load the page. Redirecting...');
            window.location.href = url;
        }
    };

    const setActiveLink = (url) => {
        
        let targetPathname;
        try {
            targetPathname = new URL(url).pathname;
        } catch (e) {
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
                link.classList.add('bg-red-50', 'text-red-600');
                link.classList.remove('hover:bg-gray-50', 'text-gray-700', 'md:text-gray-700');
                if (icon) icon.classList.add('text-red-600');
                if (span) {
                    span.classList.add('text-red-600');
                    span.classList.remove('text-gray-700');
                }
            } else {
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

    window.addEventListener('popstate', () => {
        loadPage(location.href, false);
    });
    
    setActiveLink(window.location.href);

});