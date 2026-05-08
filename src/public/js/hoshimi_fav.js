// Section favoris
const favSection = document.getElementById('favorites-section');
const favGrid = document.getElementById('favorites-grid');
const favEmpty = document.getElementById('favorites-empty');

if (favs.length > 0) {
    favSection.style.display = 'block';
    favGrid.style.display = 'grid';
    favEmpty.style.display = 'none';

    favs.forEach(fav => {
        const original = document.querySelector(`[data-anime-slug="${fav.slug}"]`);
        if (original) {
            // On crée la copie
            const clone = original.cloneNode(true);
            
            // On cherche le score dans le clone et on le supprime s'il existe
            const scoreBadge = clone.querySelector('.card__score');
            if (scoreBadge) {
                scoreBadge.remove();
            }
            
            // On ajoute le clone nettoyé à la grille
            favGrid.appendChild(clone);
        }
    });
}