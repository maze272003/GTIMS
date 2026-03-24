<style>
    #gtims-global-preloader {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        background: #ffffff;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }

    #gtims-global-preloader.gtims-preloader-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    #gtims-global-preloader .gtims-heart {
        font-size: 3rem;
        line-height: 1;
        color: #dc2626;
        animation: gtims-heartbeat 1.05s ease-in-out infinite;
        transform-origin: center;
    }

    #gtims-global-preloader .gtims-preloader-text {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        color: #7f1d1d;
        animation: gtims-text-fade 1.6s ease-in-out infinite;
    }

    @keyframes gtims-heartbeat {
        0%, 100% { transform: scale(1); }
        20% { transform: scale(1.15); }
        40% { transform: scale(0.95); }
        60% { transform: scale(1.1); }
        80% { transform: scale(1); }
    }

    @keyframes gtims-text-fade {
        0%, 100% { opacity: 0.45; }
        50% { opacity: 1; }
    }
</style>

<div id="gtims-global-preloader" role="status" aria-live="polite" aria-label="Page is loading">
    <div class="gtims-heart" aria-hidden="true">❤</div>
    <p class="gtims-preloader-text">She's cares</p>
</div>

<script>
    (function () {
        var preloader = document.getElementById('gtims-global-preloader');
        if (!preloader) return;

        function hidePreloader() {
            preloader.classList.add('gtims-preloader-hidden');
        }

        if (document.readyState === 'complete') {
            window.requestAnimationFrame(hidePreloader);
        } else {
            window.addEventListener('load', hidePreloader, { once: true });
        }

        window.setTimeout(hidePreloader, 7000);
    })();
</script>
