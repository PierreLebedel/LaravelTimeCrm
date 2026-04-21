# Design System MaryUI

## Objet

Ce document fixe les conventions UI legeres deja utilisees dans le projet, afin de garder une interface coherente sans alourdir l'implementation.

Il ne s'agit pas d'un design system complet, mais d'un cadre pragmatique pour les ecrans Livewire / MaryUI existants.

## Stack UI

- `MaryUI` pour les composants de base.
- `Livewire` pour les pages et la logique reactive.
- `secondnetwork/blade-tabler-icons` pour les icones.

## Principes generaux

- Reutiliser les composants MaryUI avant de creer du HTML specifique.
- Privilegier une mise en page simple, lisible et dense.
- Garder une hierarchie constante : header de page, cards de contexte, tableau ou drawer d'action.
- Les statuts metier doivent etre visibles rapidement sans surcharger l'ecran.
- Les couleurs client servent au reperage visuel des evenements et des regroupements.

## Composants standards

### Header de page

Utiliser `x-header` pour les pages principales.

Conventions :

- un `title` clair et metier ;
- un `subtitle` court ;
- `separator` par defaut ;
- actions principales dans `x-slot:actions`.

### Cards

Utiliser `x-card` pour :

- les blocs de synthese ;
- les groupes de filtres ;
- les panneaux d'information ;
- les listes hebdomadaires par jour ;
- les sections de revue.

Conventions :

- une card = une fonction lisible ;
- titre court ;
- texte d'aide discret en `text-base-content/60` ou `70`.

### Drawers

Utiliser `x-drawer` pour les formulaires de creation et d'edition.

Conventions :

- ouverture depuis un bouton d'action ou un clic sur ligne / evenement ;
- `with-close-button` par defaut ;
- actions en bas via `x-slot:actions` ;
- bouton secondaire `Annuler`, bouton principal `Enregistrer` ou `Valider`.

### Tables

Utiliser `x-table` pour les listes de gestion et la vue d'analyse.

Conventions :

- colonnes simples et metier ;
- actions en fin de ligne ;
- custom cells via `@scope(...)` si une representation visuelle est necessaire ;
- pas de surcharge visuelle inutile.

### Formulaires

Composants utilises :

- `x-input`
- `x-textarea`
- `x-select`
- `x-password`
- `x-checkbox`
- `x-toggle`

Conventions :

- labels explicites en francais ;
- placeholders select via `Choisissez` quand il n'y a pas de valeur par defaut ;
- les formulaires repetes doivent etre mutualises si possible ;
- pour le couple `client / projet`, le select `Projet` peut afficher tous les projets tant qu'aucun client n'est choisi ;
- dans ce cas, le libelle du projet inclut aussi le client pour rester lisible ;
- choisir un projet doit renseigner automatiquement le client correspondant ;
- si le client change et ne correspond plus au projet choisi, le projet est reinitialise ;
- le select dependant `Projet` doit etre desactive pendant son rechargement Livewire ;
- les dates / heures utilisent un pas de `15 minutes` ;
- les erreurs doivent rester proches du champ via le comportement standard MaryUI / Livewire.

## Conventions metier UI

### Client

- Afficher un client avec son nom et sa couleur via `x-client-indicator`.
- La couleur client doit apparaitre dans :
  - les evenements du calendrier ;
  - la revue ;
  - l'analyse ;
  - les listes ou colonnes client quand cela apporte un vrai repere.

### Statuts

Utiliser `x-badge` pour les statuts ou etiquettes courtes.

Cas actuels :

- `needs_review` : badge warning
- `formatted` / `synced` : badge principal ou discret selon le contexte
- `non facturable` : badge ghost
- activation agenda : badge success ou ghost

### Evenements

Un evenement doit montrer rapidement :

- son titre ;
- son horaire ;
- son client ;
- eventuellement son projet ;
- ses statuts utiles.

Quand la couleur client est connue, elle doit etre visible sur la carte ou le panneau de lecture.

## Boutons

Conventions actuelles :

- action principale : `btn-primary`
- action secondaire : style par defaut
- action destructive : texte `text-error`, souvent avec `tabler.trash`
- action de modification : `tabler.pencil`
- action d'ajout : `tabler.plus`
- action de synchronisation : `tabler.refresh`

## Icones

Utiliser les icones Tabler avec la notation MaryUI :

- `tabler.plus`
- `tabler.pencil`
- `tabler.trash`
- `tabler.refresh`
- `tabler.calendar-week`

Regles :

- une icone doit renforcer l'action, pas la remplacer ;
- pas d'icone decorative sans utilite metier ;
- garder les memes icones pour les memes actions dans tout le projet.

## Patterns deja valides

- page de gestion = `x-header` + `x-card` + `x-table` + `x-drawer`
- edition / creation d'evenement = drawer lateral
- formulaire evenement mutualise dans un composant Blade partage
- select dependant `client <-> projet`
- badge pour les statuts courts
- indicateur couleur pour les clients
- experimentation FullCalendar = grille JS tierce dans `wire:ignore`, mais drawer et formulaire restent ceux du systeme MaryUI

## Evolution

Ce document doit rester court et concret.

On l'etend seulement lorsqu'un nouveau pattern UI est :

- reutilise a plusieurs endroits ;
- assez stable pour devenir une convention ;
- utile pour eviter de diverger dans les prochains ecrans.
