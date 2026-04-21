# Vision produit

## Contexte

Application desktop locale construite avec NativePHP, Laravel et Livewire.

Chaque utilisateur dispose de sa propre installation locale. Il n'y a donc pas de gestion de comptes, de connexion distante ni de multi-tenant applicatif.

## Objectif

Permettre de gerer :

- des clients ;
- des projets rattaches a un client ;
- un ou plusieurs agendas DAV ;
- les evenements de ces agendas ;
- le temps passe et les couts associes par client et par projet.

## Regles metier validees

- Un projet appartient a un client.
- Un evenement synchronise doit etre associe a un client une fois classe.
- L'association a un projet est facultative.
- Le systeme doit pouvoir reformater un evenement pour respecter une convention de titre et de description.
- Le titre cible d'un evenement est : `{client}//{projet} : feature description`.
- Lorsqu'une connexion DAV revele des evenements non conformes, l'utilisateur doit les traiter un par un pour les associer correctement.
- Un client ou un projet ne peut pas etre supprime tant qu'au moins un evenement d'agenda lui est rattache.
- L'application doit offrir une vue hebdomadaire du calendrier avec navigation semaine par semaine.
- Un clic sur un evenement ouvre un drawer d'edition.
- L'edition autorise `client`, `projet`, `description courte`, `description detaillee`, `date` et `heure`.
- La saisie date / heure se fait avec une precision de `15 minutes`.
- Lorsqu'un evenement est edite ou passe en revue, son titre distant doit etre reecrit.
- Les synchronisations DAV et les pushes distants utilisent le systeme de jobs Laravel.
- L'application doit permettre de visualiser la file de jobs et les traitements en cours.
- La fenetre de synchronisation CalDAV est limitee a `3 mois passes` et `6 mois futurs`.
- L'application doit offrir une vue tabulaire ou liste permettant d'agreger le temps passe sur une periode.
- Chaque client peut porter une tarification au temps pour calculer un cout total.
- La facturation journaliere repose actuellement sur `1 jour = 7 heures`.
- La synchronisation doit etre lancee au demarrage de l'application, avec possibilite de forcer une resynchronisation.
- En cas de conflit, la source distante gagne et l'evenement revient dans le flux de revue.

## Principes UX proposes

- Une page `Calendrier` centree sur la semaine courante.
- Une page `Agendas` pour connecter les agendas DAV et forcer une synchronisation.
- La page `Agendas` permet aussi d'activer ou desactiver chaque agenda distant.
- Une page `Revue` pour retraiter les evenements non conformes.
- Une page `Queue` pour suivre les jobs en attente, en cours et echoues.
- Une page `Analyse` pour les totaux par periode, filtrables par client, projet et agenda.
- Les formulaires, tableaux et panneaux de gestion s'appuient sur MaryUI.

## Hypotheses de travail

- Le temps passe est calcule a partir de la duree reelle des evenements calendaires.
- Les evenements calendaires sont importes puis synchronises localement, avec conservation d'un identifiant externe stable.
- L'association client/projet vit dans la base locale meme si le titre de l'evenement sert aussi de convention visible dans l'agenda.
- Le worker de queue est lance a la demande par l'application NativePHP pour traiter les jobs de fond.
