document.addEventListener("DOMContentLoaded", () => {
    const bars = document.querySelectorAll(".progress-bar");
    bars.forEach((bar, i) => {
        const width = bar.getAttribute("aria-valuenow") + "%";
        setTimeout(() => {
            bar.style.width = width;
        }, i * 100); // leichte Verzögerung für jeden Balken
    });
});