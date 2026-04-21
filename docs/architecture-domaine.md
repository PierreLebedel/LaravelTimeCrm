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
- `billing_mode` : `hourly` ou `daily`
- `hourly_rate`
- `daily_rate`
- `is_active`

Regles :

- un client possede plusieurs projets ;
- un client peut etre archive ;
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
- `is_active`
- `last_synced_at`

Notes :

- le secret est stocke chiffre via cast Eloquent ;
- la synchronisation est queuee au demarrage NativePHP ;
- un bouton de synchronisation manuelle existe sur la page `Agendas`.

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

### Attribution client / projet

1. Le titre distant est parse selon la convention `{client}//{projet} : feature description`.
2. Si le client existe, l'evenement est relie au client.
3. Si le projet est absent ou vaut `Sans projet`, l'evenement reste sans projet.
4. Si le titre est invalide ou si la reference locale est introuvable, l'evenement passe en revue.

### Conflits

- la source distante gagne ;
- si un evenement deja connu devient incoherent apres synchro, ses associations locales sont nettoyees ;
- l'evenement repasse en `needs_review` avec `sync_status = conflict`.

### Queue applicative

- les jobs sont stockes dans la base locale ;
- l'application lance un worker court a la demande ;
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

### UI Livewire

- pages full-page en SFC via `Route::livewire()` ;
- MaryUI pour les formulaires, tableaux, drawers et navigation ;
- icones Tabler via `secondnetwork/blade-tabler-icons` ;
- notation `tabler.nom-icone` dans les composants MaryUI.

## Limites actuelles

- la synchronisation importe les `VEVENT` simples, sans gestion avancee des recurrents ;
- le `PUT` distant reecrit actuellement les champs principaux de l'evenement, pas un payload CalDAV exhaustif ;
- le `PUT` distant preserve deja les proprietes VEVENT inconnues les plus courantes, mais n'est pas encore un merge CalDAV complet ;
- la fenetre de synchro est fixe pour l'instant et n'est pas encore parametrable dans l'UI.
