"use strict";

// Run JavaScript after the HTML is loaded
document.addEventListener("DOMContentLoaded", function () {
    // Confirm lesson or booking cancellation
    const cancelForms = document.querySelectorAll(
        ".cancel-booking-form, .admin-lessons-page .inline-form",
    );

    cancelForms.forEach(function (form) {
        const actionInput = form.querySelector('input[name="action"]');

        // Only confirm cancellation actions
        if (
            form.classList.contains("cancel-booking-form") ||
            (actionInput && actionInput.value === "cancel")
        ) {
            form.addEventListener("submit", function (event) {
                const confirmed = window.confirm(
                    "Are you sure you want to cancel this booking or lesson?",
                );

                if (!confirmed) {
                    event.preventDefault();
                }
            });
        }
    });

    // Automatically calculate lesson end time (60 minutes)
    const startTimeInput = document.querySelector('.admin-lessons-page input[name="start_time"]');

    const endTimeInput = document.querySelector('.admin-lessons-page input[name="end_time"]');

    if (startTimeInput && endTimeInput) {
        startTimeInput.addEventListener("change", function () {
            if (startTimeInput.value === "") {
                endTimeInput.value = "";
                return;
            }

            const timeParts = startTimeInput.value.split(":");

            const hours = Number(timeParts[0]);
            const minutes = Number(timeParts[1]);

            const totalMinutes = hours * 60 + minutes + 60;

            // Do not allow the lesson to cross midnight
            if (totalMinutes >= 1440) {
                endTimeInput.value = "";

                alert("The lesson must end on the same day. Choose an earlier start time.");

                return;
            }

            const endHours = Math.floor(totalMinutes / 60);
            const endMinutes = totalMinutes % 60;

            endTimeInput.value =
                String(endHours).padStart(2, "0") + ":" + String(endMinutes).padStart(2, "0");
        });
    }

    // Prevent repeated form submissions
    const forms = document.querySelectorAll("form");

    forms.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (event.defaultPrevented) {
                return;
            }

            if (form.dataset.submitting === "true") {
                event.preventDefault();
                return;
            }

            // Allow browser HTML validation before locking
            if (!form.checkValidity()) {
                return;
            }

            form.dataset.submitting = "true";

            const submitButton = form.querySelector('button[type="submit"]');

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = "Processing...";
            }
        });
    });
});
