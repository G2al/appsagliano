var swiper = new Swiper(".banner1", {
    slidesPerView: 1.2,
    spaceBetween: 15,
    loop: false,
});

var sliderTwo = new Swiper(".review", {
    slidesPerView: 1.5,
    spaceBetween: 15,
    loop: false,
});

var swiperThree = new Swiper(".driver-onboarding", {
    slidesPerView: 1,
    spaceBetween: 0,
    loop: false,
});

var thumbnailslider2 = new Swiper(".thumbnail-slider2", {
    spaceBetween: 10,
    slidesPerView: 1,
    freeMode: true,
    watchSlidesProgress: true,
});
var mainslider = new Swiper(".main-slider", {
    spaceBetween: 10,
    navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
    },
    thumbs: {
        swiper: thumbnailslider2,
    },
});

var thumbnailslider3 = new Swiper(".driver-thumbnail-slider", {
    spaceBetween: 10,
    slidesPerView: 1,
    freeMode: true,
    watchSlidesProgress: true,
});
var mainslider = new Swiper(".driver-main-slider", {
    spaceBetween: 10,
    navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
    },
    thumbs: {
        swiper: thumbnailslider3,
    },
});
// Function to handle redirection on the last slide
mainslider.on("slideChange", function() {
    const totalSlides = mainslider.slides.length - 1; // Get total number of slides (0-indexed)

    // Check if user is on the last slide
    if (mainslider.activeIndex === totalSlides) {
        document
            .querySelector(".driver-onboarding-button")
            .addEventListener("click", function() {
                // Redirect to another page on the last slide's next button click
                window.location.href = "login.html"; // Replace 'nextPage.html' with your target page
            });
    }
});