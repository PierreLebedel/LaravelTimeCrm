# Architecture domaine

## Etat actuel

Le projet contient maintenant :

- les entites `Client`, `Project`, `CalendarAccount`, `Calendar` et `CalendarEvent` ;
- les enums dans `app/Enums` ;
- un shell Livewire 4 + MaryUI pour les pages principales ;
- une couche CalDAV dans `app/Support/CalDav` ;
- un editeur metier `App\Support\CalendarEventEditor` ;
- une file de jobs visible depuis l'application ;
- des jobs Laravel pour la synchronisation et la reecriture distante.

## Modele metier

### Client

Champs :

- `name`
- `color`
- `billing_mode` : `hourly` ou `daily`
- `hourly_rate`
- `daily_rate`
- `is_active`

Regles :

- un client possede plusieurs projets ;
- un client peut etre archive ;
- un client porte une couleur de reference pour les evenements et les lignes d'analyse ;
- suppression interdite si des evenements synchronises existent ;
- le calcul journalier repose actuellement sur `1 jour = 7 heures`.

### Project

Champs :

- `client_id`
- `name`
- `description`
- `is_active`

Regles :

- un projet appartient a un client ;
- le projet est facultatif pour un evenement ;
- suppression interdite si des evenements synchronises existent.

### CalendarAccount

Represente une connexion DAV.

Champs :

- `name`
- `base_url`
- `username`
- `password`
- `default_client_id`
- `is_active`
- `last_synced_at`

Notes :

- le secret est stocke chiffre via cast Eloquent ;
- la synchronisation est queuee au demarrage NativePHP ;
- un bouton de synchronisation manuelle existe sur la page `Agendas`.
- un compte peut imposer un client par defaut pour toutes ses importations locales.

### Calendar

Represente un agenda distant decouvert depuis un compte DAV.

Champs :

- `calendar_account_id`
- `external_id`
- `name`
- `color`
- `timezone`
- `is_selected`

Regles :

- un agenda distant peut etre decouvert sans etre synchronise ;
- seuls les agendas `is_selected = true` importent leurs evenements.

### CalendarEvent

Represente la copie locale d'un evenement distant.

Champs :

- `calendar_id`
- `client_id`
- `project_id`
- `ical_uid`
- `external_id`
- `external_etag`
- `starts_at`
- `ends_at`
- `timezone`
- `title`
- `description`
- `is_billable`
- `feature_description`
- `sync_status`
- `format_status`
- `source_updated_at`
- `last_synced_at`

Statuts en place :

- `sync_status` : `queued`, `synced`, `conflict`, `orphaned`
- `format_status` : `formatted`, `needs_review`, `ignored`

## Flux metier implementes

### Synchronisation CalDAV

1. L'utilisateur cree un compte DAV.
2. Un job `SyncCalendarAccountJob` est dispatch.
3. Le synchroniseur decouvre les calendriers via `PROPFIND`.
4. Chaque calendrier distant est upserte localement.
5. Les evenements `VEVENT` sont recuperes via `REPORT`.
6. La requete CalDAV applique une fenetre temporelle de `3 mois passes` et `6 mois futurs`.
7. Chaque evenement est upserte localement avec son `etag` et son `UID`.
8. `last_synced_at` est mis a jour sur le compte et l'evenement.

### Edition d'un evenement

1. L'utilisateur clique un evenement dans le calendrier ou le traite depuis la revue.
2. L'application ouvre un drawer ou un panneau d'edition.
3. L'utilisateur peut modifier :
   - client ;
   - projet ;
   - description courte ;
   - description detaillee ;
   - debut ;
   - fin.
4. La precision de saisie est fixee a `15 minutes`.
5. Le titre local est reecrit automatiquement.
6. Un job `PushCalendarEventToRemoteJob` est dispatch pour pousser la mise a jour distante.

### Calendrier FullCalendar

1. La page `Calendrier` expose une vue `timeGridWeek` FullCalendar.
2. Les evenements locaux de la semaine visible y sont projetes avec leur couleur client.
3. Un clic ouvre le drawer metier partage pour l'edition.
4. Une selection de plage ouvre la creation d'evenement avec agenda, client, projet, titre et description.
5. Un drag and drop ou un resize met a jour `starts_at` et `ends_at`, puis queue un `PushCalendarEventToRemoteJob`.

### Creation d'un evenement

1. Depuis la vue FullCalendar, l'utilisateur cree un evenement en selectionnant une plage.
2. Le drawer mutualise le meme formulaire que l'edition et la revue.
3. En creation, le formulaire ajoute le choix de l'agenda cible.
4. L'application genere immediatement un `UID` iCal et un chemin `.ics` local pour reutiliser le pipeline de push distant existant.
5. Un job `PushCalendarEventToRemoteJob` est ensuite queue pour creer la ressource distante.

### Attribution client / projet

1. Le titre distant est parse selon la convention `Client/Projet : Title` ou `Client : Title`.
2. Si le client existe, l'evenement est relie au client.
3. Si le projet est absent, l'evenement reste sans projet.
4. Si le titre est invalide ou si la reference locale est introuvable, l'evenement passe en revue.
5. Dans les formulaires d'edition, tant qu'aucun client n'est choisi, tous les projets restent visibles.
6. Si un projet est choisi en premier, le client correspondant est selectionne automatiquement.
7. Si le client change ensuite vers un autre client, le projet est reinitialise s'il n'est plus compatible.
8. Si le client choisi n'a qu'un seul projet, celui-ci est selectionne automatiquement.
9. Si le client choisi a plusieurs projets, le champ `Projet` devient obligatoire.
10. Si le client choisi n'a aucun projet, le champ `Projet` reste visible mais desactive.

### Facturation des evenements

- un evenement interne doit etre rattache a un client dedie ;
- un evenement `non facturable` est marque par une case a cocher sur l'evenement ;
- un evenement `non facturable` reste synchronise et visible, mais il est exclu des agregats de la page `Analyse`.

### Client par defaut DAV

1. Un `CalendarAccount` peut pointer vers un `default_client_id`.
2. Si ce champ est renseigne, tous les evenements importes depuis ce compte sont relies localement a ce client.
3. Cette affectation locale ne declenche pas de `PUT` distant a elle seule.
4. Le projet reste optionnel :
   si le titre distant contient un projet valide pour ce client, il est rattache ;
   sinon l'evenement reste sans projet.

### Conflits

- la source distante gagne ;
- si un evenement deja connu devient incoherent apres synchro, ses associations locales sont nettoyees ;
- l'evenement repasse en `needs_review` avec `sync_status = conflict`.

### Queue applicative

- les jobs sont stockes dans la base locale ;
- NativePHP execute le worker associe a la queue `default` ;
- un tableau de bord affiche :
  - les jobs en attente ;
  - les jobs reserves ;
  - les jobs en cours ;
  - les jobs echoues.

## Decoupage applicatif

### Backend Laravel

- modeles Eloquent pour les entites metier ;
- enums centralisees dans `app/Enums` ;
- synchronisation et push CalDAV dans `app/Support` ;
- parsing de titre dans `CalendarEventTitleParser` ;
- formatage de titre dans `CalendarEventTitleFormatter` ;
- jobs dans `app/Jobs`.
- aucun lanceur applicatif manuel du worker n'est conserve dans le code metier.

### UI Livewire

- pages full-page en SFC via `Route::livewire()` ;
- MaryUI pour les formulaires, tableaux, drawers et navigation ;
- icones Tabler via `secondnetwork/blade-tabler-icons` ;
- notation `tabler.nom-icone` dans les composants MaryUI ;
- selects client/projet avec option par defaut `Choisissez` ;
- selects `Client` et `Projet` affiches sur la meme ligne dans les formulaires d'evenement ;
- select `Projet` desactive pendant son rechargement Livewire ;
- select `Projet` requis si le client courant possede au moins un projet ;
- select `Projet` automatiquement renseigne si le client courant n'a qu'un seul projet ;
- le couple `client / projet` fonctionne dans les deux sens dans les formulaires d'evenement ;
- FullCalendar constitue maintenant la vue calendrier principale.
- champs creation / edition / revue mutualises via un composant Blade partage.

## Limites actuelles

- la synchronisation importe les `VEVENT` simples, sans gestion avancee des recurrents ;
- le `PUT` distant reecrit actuellement les champs principaux de l'evenement, pas un payload CalDAV exhaustif ;
- le `PUT` distant preserve deja les proprietes VEVENT inconnues les plus courantes, mais n'est pas encore un merge CalDAV complet ;
- la fenetre de synchro est fixe pour l'instant et n'est pas encore parametrable dans l'UI.
