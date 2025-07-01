document.addEventListener('scroll', function () {
  const parallax = document.querySelector('.parallax-image');
  if (!parallax) return;

  // langsamer scrollen: Faktor z.B. 0.5
  const scrolled = window.pageYOffset;
  parallax.style.transform = 'translateY(' + (scrolled * 0.5) + 'px)';
});