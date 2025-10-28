// public/js/sidebar.js

document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const desktopCollapseBtn = document.getElementById('desktop-collapse-btn');
    const contentWrapper = document.getElementById('content-wrapper');
    const header = document.querySelector('header');
    const navTextElements = document.querySelectorAll('.nav-text');
    const navIcons = document.querySelectorAll('.nav-icon');
    const navLinkElements = document.querySelectorAll('.nav-link'); // Get all navigation links

    // --- Mobile Menu Functions ---
    const openSidebar = () => {
        sidebar.classList.remove('translate-x-[-100%]');
        overlay.classList.remove('hidden');
    };
    const closeSidebar = () => {
        sidebar.classList.add('translate-x-[-100%]');
        overlay.classList.add('hidden');
    };

    mobileMenuBtn?.addEventListener('click', () => {
        if (sidebar.classList.contains('translate-x-[-100%]')) {
            openSidebar();
        } else {
            closeSidebar();
        }
    });

    overlay?.addEventListener('click', closeSidebar);

    // --- Desktop Collapse Function ---
    desktopCollapseBtn?.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.toggle('lg:w-20');
        sidebar.classList.toggle('lg:w-64', !isCollapsed);
        contentWrapper?.classList.toggle('lg:ml-20', isCollapsed);
        contentWrapper?.classList.toggle('lg:ml-64', !isCollapsed);
        header?.classList.toggle('lg:left-20', isCollapsed);
        header?.classList.toggle('lg:left-64', !isCollapsed);
        navTextElements.forEach(link => link.classList.toggle('lg:hidden', isCollapsed));
        navIcons.forEach(icon => icon.classList.toggle('lg:mx-auto', isCollapsed));
        const icon = desktopCollapseBtn.querySelector('i');
        icon.classList.toggle('fa-chevron-left', !isCollapsed);
        icon.classList.toggle('fa-chevron-right', isCollapsed);
    });

    // --- Active Link Styling for Standard Navigation ---
    // This function runs once on page load to highlight the current page's link.
    const setActiveLink = () => {
        const currentUrl = window.location.href;
        
        navLinkElements.forEach(link => {
            const icon = link.querySelector('i');
            const span = link.querySelector('span');

            // Check if the link's href matches the current URL
            if (link.href === currentUrl) {
                link.classList.add('bg-red-50', 'text-red-600');
                link.classList.remove('hover:bg-gray-50', 'text-gray-700', 'md:text-gray-700');
                if (icon) {
                    icon.classList.add('text-red-600');
                    icon.classList.remove('text-gray-600');
                }
                if (span) {
                    span.classList.add('text-red-600');
                    span.classList.remove('text-gray-700');
                }
            } else {
                link.classList.remove('bg-red-50', 'text-red-600');
                link.classList.add('hover:bg-gray-50', 'text-gray-700', 'md:text-gray-700');
                 if (icon) {
                    icon.classList.remove('text-red-600');
                    icon.classList.add('text-gray-600');
                }
                if (span) {
                    span.classList.remove('text-red-600');
                    span.classList.add('text-gray-700');
                }
            }
        });
    };

    // Set the active link when the page finishes loading
    setActiveLink();
});