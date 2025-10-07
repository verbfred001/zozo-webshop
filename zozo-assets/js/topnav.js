document.addEventListener("DOMContentLoaded", function () {
  const topnav = document.querySelector(".topnav");
  const navbar = document.querySelector(".navbar");
  let lastScrollY = window.scrollY;

  window.addEventListener("scroll", function () {
    if (window.scrollY > lastScrollY) {
      // Naar beneden scrollen: verberg de topnav en schuif de navbar omhoog
      topnav.classList.add("hidden");
      navbar.classList.add("no-topnav");
    } else {
      // Naar boven scrollen: toon de topnav en schuif de navbar omlaag
      topnav.classList.remove("hidden");
      navbar.classList.remove("no-topnav");
    }
    lastScrollY = window.scrollY;
  });
});