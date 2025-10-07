document.addEventListener('DOMContentLoaded', () => {
  const menuToggle = document.getElementById('mobile-menu');
  const navLinks = document.querySelector('.nav-links');
  const dropdownParents = document.querySelectorAll('.has-dropdown');

  menuToggle.addEventListener('click', () => {
    navLinks.classList.toggle('active');
  });

  // Mobiel submenu toggles
  dropdownParents.forEach(item => {
    item.addEventListener('click', (e) => {
      if (window.innerWidth <= 768) {
        // Alleen submenu openen als menu zichtbaar is
        if (!navLinks.classList.contains('active')) return;
        e.stopPropagation();
        // Sluit andere open submenu's op hetzelfde niveau
        const parent = item.parentElement;
        parent.querySelectorAll(':scope > .has-dropdown.active').forEach(function(subitem) {
          if (subitem !== item) subitem.classList.remove('active');
        });
        item.classList.toggle('active');
      }
    });
  });


  // Desktop dropdowns openen/sluiten op klik (alle niveaus)
  document.querySelectorAll('.has-dropdown > .nav-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
      if (window.innerWidth > 768) { // Alleen desktop
        e.preventDefault();
        e.stopPropagation();
        // Sluit andere open dropdowns op hetzelfde niveau
        const parent = link.parentElement.parentElement;
        parent.querySelectorAll(':scope > .has-dropdown.active').forEach(function(item) {
          if (item !== link.parentElement) item.classList.remove('active');
        });
        // Toggle deze dropdown
        link.parentElement.classList.toggle('active');
      }
    });
  });

  // Klik buiten menu sluit alles (desktop)
  document.addEventListener('click', function(e) {
    if (window.innerWidth > 768 && !e.target.closest('.navbar')) {
      document.querySelectorAll('.has-dropdown.active').forEach(function(item) {
        item.classList.remove('active');
      });
    }
  });
});
