# Questions ouvertes

## Metier

- Un projet doit-il avoir un nom unique globalement, ou seulement a l'interieur d'un client ?

## Synchronisation DAV

- Faut-il gerer plus tard les evenements recurrents dans la premiere synchro, ou les laisser hors perimetre au debut ?
  Piste envisagee : integration de la librairie `Recur`.

## UX

- La vue d'analyse doit-elle agreger par client, projet, agenda, ou combinaison libre de filtres ?
- Souhaites-tu un ecran plus detaille sur les jobs echoues avec le payload complet ?

## Implementation

- Faut-il documenter un mini design system MaryUI interne avec conventions de boutons, tableaux, drawers et icones Tabler ?
- Souhaites-tu ajuster plus finement les parametres du worker `default` pilote par NativePHP ?

## Decisions en attente

- [x] Mode de calcul du cout journalier
- [x] Politique de synchronisation automatique
- [x] Fenetre temporelle de synchronisation
- [x] Strategie de resolution des conflits
- [x] Portee des modifications distantes
- [x] Format des composants Livewire
- [x] Utilisation de MaryUI pour les interfaces de gestion
- [x] Emplacement des enums dans `app/Enums`
- [x] Utilisation de `blade-tabler-icons` pour les icones UI
- [x] Suppression du socle `users` et des artefacts d'authentification inutiles
- [x] Activation ou non calendrier par calendrier
- [x] Edition d'evenement depuis le calendrier
- [x] Precision de saisie date / heure a 15 minutes
- [x] Usage des jobs Laravel pour synchro et push distant
- [x] Visibilite minimale sur la file de jobs
- [x] Nouveau format de titre `Client/Projet : Title` ou `Client : Title`
- [x] Couleur metier sur les clients
- [x] Client par defaut optionnel sur les comptes DAV
- [x] Creation d'evenement depuis un jour du calendrier
- [x] Evenements internes via client dedie
- [x] Evenements non facturables exclus de l'analyse
