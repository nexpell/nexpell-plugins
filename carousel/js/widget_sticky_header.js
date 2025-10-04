document.addEventListener("DOMContentLoaded", () => {
  const header = document.getElementById("sticky_header");
  window.addEventListener("scroll", () => {
    if (window.scrollY > 50) {
      header.classList.add("shrink");
    } else {
      header.classList.remove("shrink");
    }
  });
});

