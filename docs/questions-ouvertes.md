# Questions ouvertes

## Metier

- Un projet doit-il avoir un nom unique globalement, ou seulement a l'interieur d'un client ?
- Faut-il prevoir un statut `non facturable` ou `interne` pour certains evenements ?

## Synchronisation DAV

- Veux-tu modifier uniquement le titre distant, ou aussi la description distante selon un template maitrise ?
- Faut-il gerer plus tard les evenements recurrents dans la premiere synchro, ou les laisser hors perimetre au debut ?
- Veux-tu pouvoir activer ou desactiver calendrier par calendrier apres decouverte ?

## UX

- La vue d'analyse doit-elle agreger par client, projet, agenda, ou combinaison libre de filtres ?
- Faut-il une exportabilite des syntheses, par exemple CSV ?
- Souhaites-tu un ecran plus detaille sur les jobs echoues avec le payload complet ?

## Implementation

- Souhaites-tu garder la table `users` du squelette Laravel, ou la supprimer rapidement puisque l'application n'a pas d'authentification ?
- Faut-il documenter un mini design system MaryUI interne avec conventions de boutons, tableaux, drawers et icones Tabler ?
- Souhaites-tu qu'un worker de queue reste vivant plus longtemps en arriere-plan, ou prefieres-tu conserver un worker court lance a la demande ?

## Decisions en attente

- [x] Mode de calcul du cout journalier
- [x] Politique de synchronisation automatique
- [x] Fenetre temporelle de synchronisation
- [x] Strategie de resolution des conflits
- [ ] Portee des modifications distantes
- [x] Format des composants Livewire
- [x] Utilisation de MaryUI pour les interfaces de gestion
- [x] Emplacement des enums dans `app/Enums`
- [x] Utilisation de `blade-tabler-icons` pour les icones UI
- [ ] Suppression ou non du socle d'authentification Laravel
- [x] Activation ou non calendrier par calendrier
- [x] Edition d'evenement depuis le calendrier
- [x] Precision de saisie date / heure a 15 minutes
- [x] Usage des jobs Laravel pour synchro et push distant
- [x] Visibilite minimale sur la file de jobs
