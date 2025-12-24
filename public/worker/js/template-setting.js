/*====================
  RTL js
======================*/
const dirSwitch = document.querySelector("#dir-switch");
const htmlDom = document.querySelector("html");
const rtlLink = document.querySelector("#rtl-link");
const initialCheck = localStorage.getItem("dir");

if (dirSwitch) {
    if (initialCheck === "rtl") dirSwitch.checked = true;
    
    dirSwitch.addEventListener("change", (e) => {
        const checkbox = e.target;
        console.log(checkbox.checked);
        if (checkbox.checked) {
            if (htmlDom) htmlDom.setAttribute("dir", "rtl");
            if (rtlLink) rtlLink.href = "css/vendors/bootstrap.rtl.min.css";
            localStorage.setItem("rtlcss", "css/vendors/bootstrap.rtl.min.css");
            localStorage.setItem("dir", "rtl");
        }

        if (!checkbox.checked) {
            if (htmlDom) htmlDom.setAttribute("dir", "ltr");
            if (rtlLink) rtlLink.href = "css/vendors/bootstrap.css";
            localStorage.setItem("rtlcss", "css/vendors/bootstrap.css");
            localStorage.setItem("dir", "ltr");
        }
    });
}

// Rtl - CON CONTROLLI
if (htmlDom) {
    htmlDom.setAttribute(
        "dir",
        localStorage.getItem("dir") ? localStorage.getItem("dir") : "ltr"
    );
}

if (rtlLink) {
    rtlLink.href = localStorage.getItem("rtlcss") ?
        localStorage.getItem("rtlcss") :
        "css/vendors/bootstrap.css";
}

/*====================
  Dark js
 ======================*/
const darkSwitch = document.querySelector("#dark-switch");
const bodyDom = document.querySelector("body");
const initialDarkCheck = localStorage.getItem("layout_version");

if (darkSwitch) {
    if (initialDarkCheck === "dark") darkSwitch.checked = true;
    
    darkSwitch.addEventListener("change", (e) => {
        const checkbox = e.target;
        if (checkbox.checked) {
            if (bodyDom) bodyDom.classList.add("dark");
            localStorage.setItem("layout_version", "dark");
        }

        if (!checkbox.checked) {
            if (bodyDom) bodyDom.classList.remove("dark");
            localStorage.removeItem("layout_version");
        }
    });
}

if (bodyDom && localStorage.getItem("layout_version") == "dark") {
    bodyDom.classList.add("dark");
}