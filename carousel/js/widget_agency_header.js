document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  // Widget suchen – per ID, Klasse oder Attribut
  const widget = document.getElementById("widget_agency_header");

  // Navbar Script ausführen
  const navbar = document.getElementById("mainNavbar");

  const navbarStyles = [
    {
      transparentClass: "bg-light-transparent",
      solidClass: "bg-light"
    },
    {
      transparentClass: "bg-primary-transparent",
      solidClass: "bg-primary"
    },
    {
      transparentClass: "bg-body-tertiary-transparent",
      solidClass: "bg-body-tertiary"
    },
    {
      transparentClass: "bg-dark-transparent",
      solidClass: "bg-dark"
    }
  ];

  let currentStyle = navbarStyles.find(style =>
    navbar.classList.contains(style.solidClass)
  );

  if (!currentStyle) {
    console.warn("Navbar background class not found, defaulting to bg-light");
    currentStyle = navbarStyles[0];
  }

  function toggleNavbar() {
    if (window.scrollY > 50) {
      navbar.classList.add(currentStyle.solidClass);
      navbar.classList.remove(currentStyle.transparentClass);
    } else {
      navbar.classList.add(currentStyle.transparentClass);
      navbar.classList.remove(currentStyle.solidClass);
    }
  }

  toggleNavbar();
  window.addEventListener("scroll", toggleNavbar);
});












