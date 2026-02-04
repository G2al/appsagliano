/*========================
    Manifest / Service Worker
 ==========================*/
if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
        navigator.serviceWorker
            .register("sw.js")
            .then((registration) => registration.update().catch(() => {}))
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
    if (!refreshBtn) return;
    if (!isStandalonePwa()) return;

    refreshBtn.classList.remove("d-none");
    refreshBtn.addEventListener("click", async () => {
        refreshBtn.disabled = true;
        refreshBtn.textContent = "Aggiornamento...";

        try {
            if ("serviceWorker" in navigator) {
                const registration = await navigator.serviceWorker.getRegistration();
                if (registration) {
                    await registration.update().catch(() => {});
                }
            }

            if ("caches" in window) {
                const keys = await caches.keys();
                await Promise.all(
                    keys
                        .filter((key) => key.startsWith("sagliano-worker-"))
                        .map((key) => caches.delete(key))
                );
            }
        } finally {
            window.location.reload();
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
