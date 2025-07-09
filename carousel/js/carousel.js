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

document.addEventListener('scroll', function () {
  const parallax = document.querySelector('.parallax-image');
  if (!parallax) return;

  // langsamer scrollen: Faktor z.B. 0.5
  const scrolled = window.pageYOffset;
  parallax.style.transform = 'translateY(' + (scrolled * 0.5) + 'px)';
});






/*

document.addEventListener("DOMContentLoaded", function () {
  // Prüfen, ob Widget mit ID "agency" existiert
  if (!document.getElementById("agency")) {
    console.log("Widget 'agency' nicht gefunden, Navbar-Script wird nicht geladen.");
    return; // Script abbrechen, wenn Widget nicht da ist
  }

  // Dein Navbar-Script hier
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

*/


document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  // Widget suchen – per ID, Klasse oder Attribut
  const widget = document.getElementById("widget_agency_header");

  if (!widget) {
    console.log("Widget 'widget_agency_header' nicht gefunden.");
    return;
  }

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












