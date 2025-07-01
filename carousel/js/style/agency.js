document.addEventListener("DOMContentLoaded", function () {
  const navbar = document.getElementById("mainNavbar");

  // Konfiguration:
  // hier definierst du den Bootstrap-Background-Klassenname
  // und was die „colored“ Variante sein soll
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

  // automatische Erkennung, welche Klasse gerade aktiv ist
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
