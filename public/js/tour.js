/**
 * GTIMS Onboarding Tour using Driver.js
 * Multi-page guided tour that walks users through the system.
 */
document.addEventListener('DOMContentLoaded', function () {
    const TOUR_STORAGE_KEY = 'gtims_tour_step';
    const TOUR_ACTIVE_KEY  = 'gtims_tour_active';

    /**
     * Page-specific tour steps keyed by route path pattern.
     * Each entry contains an array of Driver.js step configs.
     */
    const pageSteps = {
        dashboard: [
            {
                element: '#sidebar',
                popover: {
                    title: 'Sidebar Navigation',
                    description: 'This is your main navigation panel. Use it to access all system modules like Inventory, Orders, and Reports.',
                    side: 'right',
                    align: 'start'
                }
            },
            {
                element: '#main-content',
                popover: {
                    title: 'Analytics Dashboard',
                    description: 'This is your analytics dashboard. It shows key performance indicators, stock levels, and predictive charts to help you make informed decisions.',
                    side: 'left',
                    align: 'start'
                }
            },
            {
                element: '#dark-mode-toggle',
                popover: {
                    title: 'Dark Mode Toggle',
                    description: 'Switch between light and dark themes for comfortable viewing in any environment.',
                    side: 'bottom',
                    align: 'center'
                }
            },
            {
                popover: {
                    title: 'Continue the Tour',
                    description: 'Great! You\'ve seen the Dashboard. Click "Next" to continue the tour on the Inventory page, or "Skip" to end the tour here.'
                }
            }
        ],
        inventory: [
            {
                element: '#main-content',
                popover: {
                    title: 'Inventory Management',
                    description: 'This is where you manage all products and stock items. You can view current stock levels, add new products, and track batch information.',
                    side: 'left',
                    align: 'start'
                }
            },
            {
                element: '#sidebar',
                popover: {
                    title: 'Quick Navigation',
                    description: 'Use the sidebar to quickly jump between modules. Try the Order Stock or Patient Records sections next.',
                    side: 'right',
                    align: 'start'
                }
            },
            {
                popover: {
                    title: 'Continue the Tour',
                    description: 'You\'ve explored the Inventory page. Click "Next" to see the Orders section, or "Skip" to end the tour.'
                }
            }
        ],
        orders: [
            {
                element: '#main-content',
                popover: {
                    title: 'Order Management',
                    description: 'Create and manage stock orders here. You can submit new orders, track pending approvals, and view order history.',
                    side: 'left',
                    align: 'start'
                }
            },
            {
                element: '#sidebar',
                popover: {
                    title: 'Explore More',
                    description: 'There are more features to explore! Check out Holds, Requests, Suppliers, and Analytics from the sidebar.',
                    side: 'right',
                    align: 'start'
                }
            },
            {
                popover: {
                    title: 'Tour Complete! 🎉',
                    description: 'You\'ve completed the GTIMS onboarding tour! You now know the basics of the Dashboard, Inventory, and Orders. Feel free to explore the rest of the system. You can restart this tour anytime from the "Help & Tour" link in the sidebar.'
                }
            }
        ]
    };

    /**
     * Map URL path patterns to page keys
     */
    function detectCurrentPage() {
        var path = window.location.pathname;
        if (path.indexOf('/admin/inventory') !== -1)  return 'inventory';
        if (path.indexOf('/admin/orders') !== -1)      return 'orders';
        if (path.indexOf('/admin/dashboard') !== -1)   return 'dashboard';
        return null;
    }

    /**
     * Navigation map: defines the flow between pages.
     */
    var tourFlow = ['dashboard', 'inventory', 'orders'];

    var tourPageUrls = {
        dashboard: '/admin/dashboard',
        inventory: '/admin/inventory',
        orders:    '/admin/orders'
    };

    /**
     * Start or resume the tour on the current page.
     */
    function runTourOnCurrentPage() {
        var currentPage = detectCurrentPage();
        if (!currentPage) return;

        var steps = pageSteps[currentPage];
        if (!steps || steps.length === 0) return;

        var currentFlowIndex = tourFlow.indexOf(currentPage);
        var isLastPage       = currentFlowIndex === tourFlow.length - 1;
        var isFirstPage      = currentFlowIndex === 0;

        var driverObj = window.driver.js.driver({
            showProgress: true,
            animate: true,
            allowClose: true,
            overlayColor: 'rgba(0, 0, 0, 0.6)',
            stagePadding: 8,
            stageRadius: 8,
            popoverClass: 'gtims-tour-popover',
            nextBtnText: 'Next →',
            prevBtnText: '← Previous',
            doneBtnText: isLastPage ? 'Finish ✓' : 'Continue →',
            steps: steps,
            onDestroyStarted: function () {
                if (!driverObj.hasNextStep() && !isLastPage) {
                    // Navigate to next page in tour flow
                    var nextPage = tourFlow[currentFlowIndex + 1];
                    sessionStorage.setItem(TOUR_ACTIVE_KEY, 'true');
                    sessionStorage.setItem(TOUR_STORAGE_KEY, nextPage);
                    driverObj.destroy();
                    window.location.href = tourPageUrls[nextPage];
                    return;
                }
                driverObj.destroy();
                cleanupTourState();
            },
            onDestroyed: function () {
                // Tour was finished or skipped
            }
        });

        driverObj.drive();
    }

    /**
     * Clean up tour state from session storage.
     */
    function cleanupTourState() {
        sessionStorage.removeItem(TOUR_ACTIVE_KEY);
        sessionStorage.removeItem(TOUR_STORAGE_KEY);
    }

    /**
     * Initialize tour: either start fresh or resume from navigation.
     */
    function initTour() {
        // Check if we're resuming a multi-page tour
        if (sessionStorage.getItem(TOUR_ACTIVE_KEY) === 'true') {
            var expectedPage = sessionStorage.getItem(TOUR_STORAGE_KEY);
            var currentPage  = detectCurrentPage();
            if (currentPage === expectedPage) {
                // Small delay to ensure page elements are rendered
                setTimeout(runTourOnCurrentPage, 500);
            } else {
                cleanupTourState();
            }
        }
    }

    /**
     * Start the tour from the beginning (triggered by Help & Tour button).
     */
    window.startGTIMSTour = function () {
        var currentPage = detectCurrentPage();
        if (currentPage === 'dashboard') {
            // Already on dashboard, just run tour
            sessionStorage.setItem(TOUR_ACTIVE_KEY, 'true');
            sessionStorage.setItem(TOUR_STORAGE_KEY, 'dashboard');
            runTourOnCurrentPage();
        } else {
            // Navigate to dashboard to start tour
            sessionStorage.setItem(TOUR_ACTIVE_KEY, 'true');
            sessionStorage.setItem(TOUR_STORAGE_KEY, 'dashboard');
            window.location.href = tourPageUrls.dashboard;
        }
    };

    // Check if tour should resume on page load
    initTour();
});
