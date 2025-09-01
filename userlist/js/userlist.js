document.addEventListener("DOMContentLoaded", function() {
    function updateOnlineTimes() {
        document.querySelectorAll(".online-time").forEach(function(el) {
            let start = parseInt(el.getAttribute("data-start")) * 1000;
            let diff = Date.now() - start;

            let hours = Math.floor(diff / (1000 * 60 * 60));
            let minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            let seconds = Math.floor((diff % (1000 * 60)) / 1000);

            let timeStr =
                String(hours).padStart(2, '0') + ":" +
                String(minutes).padStart(2, '0') + ":" +
                String(seconds).padStart(2, '0');

            el.textContent = timeStr;
        });
    }
    updateOnlineTimes();
    setInterval(updateOnlineTimes, 1000);
});