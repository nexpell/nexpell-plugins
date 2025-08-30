function getContrastYIQ(rgbString) {
  const rgb = rgbString.replace(/[^\d,]/g, '').split(',').map(Number);
  if (rgb.length !== 3) return 'dark';
  const yiq = ((rgb[0] * 299) + (rgb[1] * 587) + (rgb[2] * 114)) / 1000;
  return yiq >= 128 ? 'dark' : 'light';
}

document.addEventListener('DOMContentLoaded', () => {
  const footer = document.querySelector('footer.footer');
  if (!footer) return;

  const bodyStyles = getComputedStyle(document.body);
  const bgColor = bodyStyles.getPropertyValue('--bs-body-bg').trim() || window.getComputedStyle(document.body).backgroundColor;

  const mode = getContrastYIQ(bgColor);

  let contrastColor, hoverColor;

  if (mode === 'dark') {
    // Heller Hintergrund → dunkler Footer mit weißem Text
    footer.classList.remove('bg-light', 'text-dark');
    footer.classList.add('bg-dark', 'text-white');
    contrastColor = '#ffffff'; // Weiß
    hoverColor = 'rgba(255, 255, 255, 0.7)';
  } else {
    // Dunkler Hintergrund → heller Footer mit schwarzem Text
    footer.classList.remove('bg-dark', 'text-white');
    footer.classList.add('bg-light', 'text-dark');
    contrastColor = '#000000'; // Schwarz
    hoverColor = 'rgba(0, 0, 0, 0.7)';
  }

  // Alle Links und Icons im Footer anpassen
  const links = footer.querySelectorAll('a, .bi');

  links.forEach(link => {
    link.style.color = contrastColor;

    link.addEventListener('mouseover', () => {
      link.style.color = hoverColor;
    });

    link.addEventListener('mouseout', () => {
      link.style.color = contrastColor;
    });
  });
});