document.addEventListener('DOMContentLoaded', function () {
  const iso = new Isotope('.isotope-container', {
    itemSelector: '.col',
    layoutMode: 'fitRows'
  });

  document.querySelectorAll('.filter-btn').forEach(button => {
    button.addEventListener('click', function () {
      const filterValue = this.getAttribute('data-filter');
      iso.arrange({ filter: filterValue });

      document.querySelectorAll('.filter-btn').forEach(btn => 
        btn.classList.remove('active', 'btn-primary')
      );
      this.classList.add('active', 'btn-primary');
    });
  });
});