<?php
/**
 * Citation du jour (carte de l'accueil) : une citation motivante et son auteur.
 * Une par jour, la même toute la journée ; la liste recommence quand elle est finie.
 * Seulement des citations dont l'auteur est connu (pas d'attribution douteuse).
 */

const CITATIONS = [
    ['Ce n\'est pas parce que les choses sont difficiles que nous n\'osons pas, c\'est parce que nous n\'osons pas qu\'elles sont difficiles.', 'Sénèque'],
    ['Un voyage de mille lieues commence toujours par un premier pas.', 'Lao Tseu'],
    ['Ce que l\'on conçoit bien s\'énonce clairement, et les mots pour le dire arrivent aisément.', 'Nicolas Boileau'],
    ['Vingt fois sur le métier remettez votre ouvrage.', 'Nicolas Boileau'],
    ['Rien ne sert de courir ; il faut partir à point.', 'Jean de La Fontaine'],
    ['Patience et longueur de temps font plus que force ni que rage.', 'Jean de La Fontaine'],
    ['Dans les champs de l\'observation, le hasard ne favorise que les esprits préparés.', 'Louis Pasteur'],
    ['L\'imagination est plus importante que le savoir.', 'Albert Einstein'],
    ['La vie, c\'est comme une bicyclette : il faut avancer pour ne pas perdre l\'équilibre.', 'Albert Einstein'],
    ['Nous sommes ce que nous faisons de manière répétée. L\'excellence n\'est donc pas un acte, mais une habitude.', 'Will Durant'],
    ['C\'est en forgeant qu\'on devient forgeron.', 'Proverbe français'],
    ['Le travail éloigne de nous trois grands maux : l\'ennui, le vice et le besoin.', 'Voltaire'],
    ['Il faut cultiver notre jardin.', 'Voltaire'],
    ['Apprendre sans réfléchir est vain ; réfléchir sans apprendre est dangereux.', 'Confucius'],
    ['Tout ce qui vaut la peine d\'être fait vaut la peine d\'être bien fait.', 'Lord Chesterfield'],
    ['Rien de grand ne s\'est accompli dans le monde sans passion.', 'Hegel'],
    ['Deviens ce que tu es.', 'Friedrich Nietzsche'],
    ['Ce qui ne me tue pas me rend plus fort.', 'Friedrich Nietzsche'],
    ['Aie le courage de te servir de ton propre entendement.', 'Emmanuel Kant'],
    ['La meilleure façon de prédire l\'avenir, c\'est de l\'inventer.', 'Alan Kay'],
    ['Les programmes doivent être écrits pour être lus par des humains, et accessoirement pour être exécutés par des machines.', 'Harold Abelson'],
    ['La simplicité est préalable à la fiabilité.', 'Edsger W. Dijkstra'],
    ['L\'optimisation prématurée est la source de tous les maux.', 'Donald Knuth'],
    ['Les mathématiques sont la reine des sciences.', 'Carl Friedrich Gauss'],
    ['En mathématiques, l\'art de poser une question doit être tenu pour plus précieux que celui de la résoudre.', 'Georg Cantor'],
    ['Dans la vie, rien n\'est à craindre, tout est à comprendre.', 'Marie Curie'],
    ['La vie n\'est facile pour aucun d\'entre nous. Mais quoi, il faut avoir de la persévérance, et surtout de la confiance en soi.', 'Marie Curie'],
    ['L\'expérience est le nom que chacun donne à ses erreurs.', 'Oscar Wilde'],
    ['Ce n\'est pas assez d\'avoir l\'esprit bon, mais le principal est de l\'appliquer bien.', 'René Descartes'],
    ['Diviser chacune des difficultés que j\'examinerais en autant de parcelles qu\'il se pourrait, et qu\'il serait requis pour les mieux résoudre.', 'René Descartes'],
    ['À vaincre sans péril, on triomphe sans gloire.', 'Pierre Corneille'],
    ['Aux âmes bien nées, la valeur n\'attend point le nombre des années.', 'Pierre Corneille'],
    ['La lecture est à l\'esprit ce que l\'exercice est au corps.', 'Joseph Addison'],
    ['Il est grand temps de rallumer les étoiles.', 'Guillaume Apollinaire'],
    ['Savoir, c\'est pouvoir.', 'Francis Bacon'],
    ['Si j\'ai vu plus loin, c\'est en montant sur les épaules de géants.', 'Isaac Newton'],
    ['La seule façon de faire du bon travail est d\'aimer ce que vous faites.', 'Steve Jobs'],
    ['Restez affamés, restez fous.', 'Steve Jobs'],
    ['Le talent, ce n\'est pas d\'écrire une page : c\'est d\'en écrire trois cents.', 'Jules Renard'],
    ['Tout est difficile avant d\'être facile.', 'Thomas Fuller'],
    ['La qualité n\'est jamais un accident ; c\'est toujours le résultat d\'un effort intelligent.', 'John Ruskin'],
    ['L\'éducation est l\'arme la plus puissante qu\'on puisse utiliser pour changer le monde.', 'Nelson Mandela'],
    ['Le génie, c\'est 1 % d\'inspiration et 99 % de transpiration.', 'Thomas Edison'],
    ['Connais-toi toi-même.', 'Inscription du temple de Delphes'],
    ['Ne remets pas à demain ce que tu peux faire aujourd\'hui.', 'Proverbe'],
];

/** Citation du jour : [texte, auteur]. */
function citation_du_jour(?int $ts = null): array
{
    $ts ??= time();
    // Numéro du jour (depuis 1970) : change à minuit, identique toute la journée.
    $jour = intdiv($ts + (int) date('Z', $ts), 86400);
    return CITATIONS[$jour % count(CITATIONS)];
}
