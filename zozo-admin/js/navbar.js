document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.has-dropdown > .nav-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      // Sluit andere open dropdowns
      document.querySelectorAll('.has-dropdown.active').forEach(function(item) {
        if (item !== link.parentElement) item.classList.remove('active');
      });
      // Toggle deze dropdown
      link.parentElement.classList.toggle('active');
    });
  });
  // Klik buiten menu sluit alles
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.navbar')) {
      document.querySelectorAll('.has-dropdown.active').forEach(function(item) {
        item.classList.remove('active');
      });
    }
  });
});