/*========================
    Manifest / Service Worker
 ==========================*/
const WORKER_APP_VERSION = "7";
const WORKER_CACHE_PREFIX = "vialo-worker-";
const WORKER_SW_URL = `sw.js?v=${WORKER_APP_VERSION}`;
let workerRefreshTriggered = false;

const hardRefreshWorkerApp = () => {
    if (workerRefreshTriggered) return;
    workerRefreshTriggered = true;

    const nextUrl = new URL(window.location.href);
    nextUrl.searchParams.set("appv", WORKER_APP_VERSION);
    nextUrl.searchParams.set("ts", Date.now().toString());
    window.location.replace(nextUrl.toString());
};

const clearWorkerCaches = async () => {
    if (!("caches" in window)) return;

    const keys = await caches.keys();
    await Promise.all(
        keys
            .filter((key) => key.startsWith(WORKER_CACHE_PREFIX))
            .map((key) => caches.delete(key))
    );
};

const forceLatestWorker = async () => {
    if (!("serviceWorker" in navigator)) return null;

    const registration = await navigator.serviceWorker.register(WORKER_SW_URL);
    await registration.update().catch(() => {});

    if (registration.waiting) {
        registration.waiting.postMessage({ type: "SKIP_WAITING" });
        return registration;
    }

    const installingWorker = registration.installing;
    if (!installingWorker) {
        return registration;
    }

    await new Promise((resolve) => {
        const timeoutId = window.setTimeout(resolve, 3000);
        installingWorker.addEventListener("statechange", () => {
            if (
                installingWorker.state === "installed" ||
                installingWorker.state === "activated" ||
                installingWorker.state === "redundant"
            ) {
                window.clearTimeout(timeoutId);
                if (registration.waiting) {
                    registration.waiting.postMessage({ type: "SKIP_WAITING" });
                }
                resolve();
            }
        });
    });

    return registration;
};

if ("serviceWorker" in navigator) {
    navigator.serviceWorker.addEventListener("controllerchange", () => {
        hardRefreshWorkerApp();
    });

    window.addEventListener("load", () => {
        forceLatestWorker()
            .catch(() => {
                /* ignore if sw not available */
            });
    });
}

/*====================
 PWA refresh button (login only)
=======================*/
const isStandalonePwa = () =>
    (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches) ||
    window.navigator.standalone === true;

window.addEventListener("load", () => {
    const refreshBtn = document.getElementById("pwa-refresh-btn");
    const versionLabel = document.getElementById("pwa-version-label");
    if (!refreshBtn) return;
    if (!isStandalonePwa()) return;

    refreshBtn.classList.remove("d-none");
    if (versionLabel) {
        let versionText = "Versione app";
        const manifestLink = document.querySelector('link[rel="manifest"]');
        if (manifestLink) {
            try {
                const manifestUrl = new URL(manifestLink.getAttribute("href"), window.location.href);
                const manifestVersion = manifestUrl.searchParams.get("v");
                if (manifestVersion) {
                    versionText = `Versione app: v${manifestVersion}`;
                }
            } catch (err) {
                /* ignore invalid manifest url */
            }
        }
        versionLabel.textContent = versionText;
        versionLabel.classList.remove("d-none");
    }

    refreshBtn.addEventListener("click", async () => {
        refreshBtn.disabled = true;
        refreshBtn.textContent = "Aggiornamento...";

        try {
            await forceLatestWorker();
            await clearWorkerCaches();
        } finally {
            hardRefreshWorkerApp();
        }
    });
});

/*====================
 Ratio js
=======================*/
window.addEventListener("load", () => {
    const bgImg = document.querySelectorAll(".bg-img");
    for (i = 0; i < bgImg.length; i++) {
        let bgImgEl = bgImg[i];

        if (bgImgEl.classList.contains("bg-top")) {
            bgImgEl.parentNode.classList.add("b-top");
        } else if (bgImgEl.classList.contains("bg-bottom")) {
            bgImgEl.parentNode.classList.add("b-bottom");
        } else if (bgImgEl.classList.contains("bg-center")) {
            bgImgEl.parentNode.classList.add("b-center");
        } else if (bgImgEl.classList.contains("bg-left")) {
            bgImgEl.parentNode.classList.add("b-left");
        } else if (bgImgEl.classList.contains("bg-right")) {
            bgImgEl.parentNode.classList.add("b-right");
        }

        if (bgImgEl.classList.contains("blur-up")) {
            bgImgEl.parentNode.classList.add("blur-up", "lazyload");
        }

        if (bgImgEl.classList.contains("bg_size_content")) {
            bgImgEl.parentNode.classList.add("b_size_content");
        }

        bgImgEl.parentNode.classList.add("bg-size");
        const bgSrc = bgImgEl.src;
        bgImgEl.style.display = "none";
        bgImgEl.parentNode.setAttribute(
            "style",
            `
      background-image: url(${bgSrc});
      background-size:cover;
      background-position: center;
      background-repeat: no-repeat;
      display: block;
      `
        );
    }
});
