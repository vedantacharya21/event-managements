document.addEventListener("DOMContentLoaded", function () {
    /*
    |--------------------------------------------------------------------------
    | Auto-hide success messages
    |--------------------------------------------------------------------------
    */

    const successMessages = document.querySelectorAll(".success");

    successMessages.forEach(function (message) {
        setTimeout(function () {
            message.style.transition = "opacity 0.5s ease";
            message.style.opacity = "0";

            setTimeout(function () {
                message.remove();
            }, 500);
        }, 4000);
    });

    /*
    |--------------------------------------------------------------------------
    | Confirmation dialogs
    |--------------------------------------------------------------------------
    */

    const confirmElements = document.querySelectorAll("[data-confirm]");

    confirmElements.forEach(function (element) {
        element.addEventListener("click", function (event) {
            const message = element.getAttribute("data-confirm");

            if (!confirm(message)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Mobile navigation
    |--------------------------------------------------------------------------
    */

    const menuButton = document.getElementById("mobileMenuBtn");
    const mainNav = document.getElementById("mainNav");

    if (menuButton && mainNav) {
        menuButton.addEventListener("click", function () {
            mainNav.classList.toggle("show");

            const isExpanded = mainNav.classList.contains("show");
            menuButton.setAttribute("aria-expanded", isExpanded);
        });

        const navLinks = mainNav.querySelectorAll("a");

        navLinks.forEach(function (link) {
            link.addEventListener("click", function () {
                mainNav.classList.remove("show");
                menuButton.setAttribute("aria-expanded", "false");
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Poster preview and validation
    |--------------------------------------------------------------------------
    */

    const posterInput = document.querySelector('input[type="file"]');

    if (posterInput) {
        posterInput.addEventListener("change", function () {
            const file = posterInput.files[0];

            if (!file) {
                return;
            }

            const allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            if (!allowedTypes.includes(file.type)) {
                alert("Please select a JPG, PNG or WEBP image.");
                posterInput.value = "";
                return;
            }

            const maxSize = 5 * 1024 * 1024;

            if (file.size > maxSize) {
                alert("Poster image must be 5 MB or smaller.");
                posterInput.value = "";
                return;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent accidental double submission
    |--------------------------------------------------------------------------
    */

    const forms = document.querySelectorAll("form");

    forms.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (form.dataset.submitted === "true") {
                event.preventDefault();
                return;
            }

            form.dataset.submitted = "true";

            const submitButtons = form.querySelectorAll(
                'button[type="submit"], input[type="submit"]'
            );

            submitButtons.forEach(function (button) {
                button.disabled = true;

                if (button.tagName === "BUTTON") {
                    button.textContent = "Processing...";
                } else {
                    button.value = "Processing...";
                }
            });
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Basic client-side date validation
    |--------------------------------------------------------------------------
    */

    const dateInputs = document.querySelectorAll('input[type="date"]');

    dateInputs.forEach(function (input) {
        if (input.hasAttribute("data-future-date")) {
            const today = new Date();

            const year = today.getFullYear();

            const month = String(today.getMonth() + 1).padStart(2, "0");

            const day = String(today.getDate()).padStart(2, "0");

            input.min = `${year}-${month}-${day}`;
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Remove URL hash after page load
    |--------------------------------------------------------------------------
    */

    if (window.location.hash === "#success") {
        window.history.replaceState(
            null,
            document.title,
            window.location.pathname + window.location.search
        );
    }
});