// On sélectionne tous les liens de la navbar
const navLinks = document.querySelectorAll('.navbar__link');

// On récupère le chemin de l'URL actuelle (ex: /films)
const currentPath = window.location.pathname;

navLinks.forEach(link => {
    // On retire la classe active partout d'abord (nettoyage)
    link.classList.remove('active');

    // On compare le href du lien avec le chemin actuel
    // link.getAttribute('href') récupère la valeur exacte (ex: "/films")
    if (link.getAttribute('href') === currentPath) {
        link.classList.add('active');
    }
});