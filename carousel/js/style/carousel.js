const slides = <?= json_encode(array_map(fn($item) => $plugin_path."images/".$item['carousel_pic'], $carouselItems)); ?>;
let currentIndex = 0;
const slide1 = document.getElementById('slide1');
const slide2 = document.getElementById('slide2');
let showingSlide1 = true;

function preloadImage(url) {
  return new Promise((resolve) => {
    const img = new Image();
    img.onload = () => resolve(url);
    img.src = url;
  });
}

async function nextSlide() {
  currentIndex = (currentIndex + 1) % slides.length;
  const nextUrl = slides[currentIndex];

  const nextLayer = showingSlide1 ? slide2 : slide1;
  const currentLayer = showingSlide1 ? slide1 : slide2;

  // Bild vorladen
  await preloadImage(nextUrl);

  // Bild im inaktiven Layer setzen
  nextLayer.style.backgroundImage = `url('${nextUrl}')`;

  // Crossfade aktivieren
  nextLayer.classList.add('active');
  nextLayer.classList.remove('inactive');
  currentLayer.classList.remove('active');
  currentLayer.classList.add('inactive');

  showingSlide1 = !showingSlide1;
}

// Alle 5 Sekunden Slide wechseln
setInterval(nextSlide, 5000);
