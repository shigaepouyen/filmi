# Filmi, spécification de conception

Date : 2026-08-10
Statut : validée

## 1. Contexte et problème

La famille (JC, Élodie, Zoé, Soline) organise une soirée ciné à la maison **tous les samedis soirs** : pizza, apéro, un film. Le choix du film alterne : une semaine ce sont les filles qui choisissent, la semaine suivante ce sont les parents.

Règles du rituel telles qu'elles existent aujourd'hui :

- **Semaine filles** : elles choisissent le film. Les parents disposent d'un droit de veto, utilisé rarement.
- **Semaine parents** : les parents proposent **trois films**, les filles tranchent parmi ces trois. Le dosage recherché est d'environ une valeur sûre et deux découvertes, l'idée étant de faire découvrir des choses et de les mettre un peu en difficulté.
- Un film déjà vu n'est pas reproposé.

**Le problème réel n'est pas la règle, c'est la production de la liste.** Personne n'arrive à sortir trois titres au moment voulu. Les idées surgissent en semaine ("tiens, il faudrait leur montrer ça") et sont oubliées le samedi soir. Élodie en particulier ne peut pas fournir de liste sur le moment.

**Filmi est donc d'abord un mémo d'idées partagé, et ensuite un outil de tirage.**

## 2. Objectifs

1. Déposer une idée de film en moins de trente secondes, depuis un téléphone, à n'importe quel moment de la semaine.
2. Ne plus jamais arriver au samedi soir avec un pool vide.
3. Produire les trois propositions de la semaine parents sans réflexion ni négociation préalable.
4. Garder la mémoire du rituel : ce qui a été vu, quand, choisi par qui, et ce que chacun en a pensé.

## 3. Non-objectifs

- Pas de gestion de comptes, de mots de passe ni d'inscription. Application familiale privée.
- Pas de lecture ni d'hébergement de contenu vidéo.
- Pas d'ouverture à d'autres familles ni de multi-tenant.
- Pas de recommandation algorithmique de films. Les humains proposent, la machine tire au sort.

## 4. Utilisateurs

Quatre profils fixes, créés à l'initialisation :

| Profil | Camp | Avatar suggéré |
|---|---|---|
| JC | adulte | Détective en trench |
| Élodie | adulte | Aviatrice |
| Zoé | enfant | Idole au micro-casque |
| Soline | enfant | Petit dinosaure |

Les avatars et couleurs sont modifiables dans l'application. Les profils sont administrables (renommage, changement d'avatar) mais leur camp (`adult` / `kid`) est structurant et n'est pas exposé à la modification courante.

### Identification

**Sélection de profil sans code.** On clique son avatar à l'arrivée, le choix est mémorisé dans un cookie longue durée. Un bouton discret "ce n'est pas moi" permet de changer de profil.

Décision assumée : n'importe qui peut techniquement voter à la place d'un autre. L'application est privée, le risque est nul, et toute friction supplémentaire (PIN) tuerait l'usage sur le canapé. Ce choix est réversible plus tard sans changement de modèle de données, puisque l'identité est déjà attachée à chaque action.

## 5. Modèle fonctionnel

### 5.1 Les deux pools

Deux pools de films en attente :

- **Pool adulte** : les films proposés par JC et Élodie.
- **Pool enfant** : les films proposés par Zoé et Soline.

Les deux pools sont **visibles de tous**. Chaque film affiche qui l'a proposé. Tout le monde peut upvoter n'importe quel film, dans les deux pools.

### 5.2 Le mémo

Chaque film porte un champ **memo** libre : pourquoi je veux leur montrer ça. C'est le cœur de la valeur de l'outil, pas un champ optionnel décoratif : c'est ce qui permet, trois semaines plus tard, de se rappeler l'intention derrière un titre.

### 5.3 Le tag de pari

Chaque film du **pool adulte** est tagué à l'ajout :

- `safe` : valeur sûre, on est à peu près certain que ça leur plaira.
- `discovery` : découverte, on veut les emmener ailleurs.

Le tag est obligatoire dans le pool adulte car il pilote le tirage. Il est facultatif dans le pool enfant, où il n'a pas d'usage.

### 5.4 Les votes

Un upvote par personne et par film, en bascule (on clique, on retire). Le compteur est visible, et le détail de qui a voté est consultable. Les votes servent à trier et à discuter, **pas** à pondérer le tirage : le tirage reste aléatoire, c'est ce qui fait le sel du rituel.

### 5.5 La séance

Une séance représente un samedi soir. Elle porte :

- une date,
- le camp qui choisit (`adult` ou `kid`), stocké **explicitement** et non recalculé,
- un indicateur de dérogation avec une note libre,
- un statut : `planned`, `done` ou `skipped`,
- le film finalement retenu,
- la shortlist des trois films proposés, le cas échéant,
- un éventuel veto avec son auteur et sa raison,
- les notes post-séance des quatre membres.

### 5.6 L'alternance, souple par construction

Le camp qui choisit par défaut est calculé depuis la **dernière séance `done`** : l'autre camp que la précédente. Ce n'est qu'une proposition.

- **Inverser le tour** : un bouton toujours accessible bascule le camp. La séance est alors marquée `derogation = 1` avec une note libre facultative ("dérogation, bulletin de Soline"). Deux semaines filles d'affilée sont donc parfaitement légitimes.
- **Samedi sans ciné** : la séance est enregistrée en `skipped`. Aucun film n'est consommé et le camp prévu est **reporté intact** à la semaine suivante, il ne saute pas son tour.

Le calcul du défaut ignorant les séances `skipped`, l'alternance reste cohérente quelle que soit la suite d'exceptions.

### 5.7 Le tirage des trois films

Déclenché les semaines parents, **sur le pool adulte uniquement**.

Algorithme :

1. Candidats : films du pool adulte en statut `pool`, hors cooldown.
2. Tirage uniforme de **1 `safe` et 2 `discovery`**.
3. Si une catégorie ne peut pas être servie, l'application le dit explicitement plutôt que de compléter en silence avec l'autre catégorie.

**Cooldown** : un film qui a figuré dans la shortlist enregistrée d'une séance et n'a pas été retenu est exclu des **deux tirages suivants**. Sans cela, les mêmes affiches reviennent chaque semaine et l'effet de surprise disparaît.

**Re-tirage illimité** : un bouton "pas dans le mood, retire" régénère la sélection autant de fois que voulu. Deux règles :

- Les shortlists intermédiaires **ne sont pas enregistrées** et ne comptent pas pour le cooldown. Seule la shortlist affichée à l'instant du choix est persistée. Sans cette règle, trois re-tirages consécutifs grilleraient la moitié du pool.
- Au sein d'une même session de tirage, le re-tirage **évite les films déjà montrés ce soir-là**, jusqu'à épuisement des candidats. Au-delà, il repart du pool complet en l'indiquant à l'écran.

### 5.8 Le veto

Les semaines filles, un parent peut poser un veto sur le film choisi. Le veto est **tracé** : auteur, film, raison libre. Un compteur de vetos par personne est affiché dans l'historique. L'objectif est l'auto-régulation sociale : le jour où quelqu'un abuse, tout le monde le voit.

Le film veto **retourne dans le pool**, il n'est pas consommé et reste proposable plus tard. Les filles choisissent alors un autre film. La séance finit donc avec un `seance_picks` en `vetoed` pour le premier film et un `chosen` pour le second. Plusieurs vetos successifs sur une même séance sont possibles, chacun laissant sa ligne.

### 5.9 Après la séance

Le film retenu passe en statut `watched` et quitte le pool. Chacun des quatre membres peut lui donner une **note sur 5**. Ces notes alimentent l'historique et le palmarès, sans aucun effet sur les tirages futurs.

### 5.10 Horaire de fin

Un réglage `default_start_time`, par défaut **19:15**, représente l'heure de démarrage habituelle du film. Chaque film affiche une heure de fin estimée, calculée comme début plus durée : "fin vers 21:40". L'écran de tirage affiche les trois heures de fin côte à côte, c'est fréquemment le critère qui départage un samedi soir avec des enfants.

### 5.11 Disponibilité en streaming

Les plateformes de visionnage sont récupérées auprès de TMDb (`watch/providers`, région FR) et affichées en pastilles sur chaque film. Savoir qu'un film n'est disponible nulle part évite de le tirer à 19h00 et de perdre la soirée. L'information est mise en cache et rafraîchie au maximum une fois par semaine et par film, les catalogues bougeant lentement.

### 5.12 Avis parental

La certification d'âge française fournie par TMDb est affichée en pastille sur chaque film. Utile compte tenu de l'écart d'âge entre Zoé et Soline. Information purement indicative, aucun blocage automatique.

### 5.13 Alerte pool faible

Un badge apparaît dès que le pool adulte descend **sous cinq films**, avec le décompte par catégorie de pari. C'est le rappel préventif qui manque aujourd'hui, en semaine et non le samedi soir dans la panique.

### 5.14 Palmarès

Une page palmarès agrège, sur une période choisie (par défaut l'année civile) : films vus, notes moyennes, meilleur film de l'année, qui a proposé le plus de films retenus, taux de veto, samedis sautés. Exportable en page imprimable autonome, dans l'esprit du Yearbook.

### 5.15 Détection de doublon

À l'ajout, si le film est déjà présent dans un pool ou déjà vu, l'application avertit avant l'enregistrement, en indiquant où il se trouve et à quelle date il a été vu. L'ajout reste possible en connaissance de cause.

## 6. Écrans

| Écran | Rôle |
|---|---|
| **Accueil / choix de profil** | Les quatre avatars. Un clic, cookie posé, redirection vers Ce samedi. |
| **Ce samedi** | Date de la prochaine séance, camp par défaut, bouton inverser le tour, bouton pas de ciné ce samedi, badge d'alerte pool faible, puis l'action de la semaine : *Tirer trois films* ou *Choisir dans la liste des filles*. |
| **Pools** | Deux onglets. Cards affiche, titre, année, durée et heure de fin, tag de pari, pastilles plateformes et âge, proposeur, compteur d'upvotes cliquable, memo dépliable. Tri par votes, récence ou durée. |
| **Ajouter un film** | Recherche TMDb en autocomplete (affiche et année pour désambiguïser les remakes), sélection, puis pool, tag de pari et memo. Bascule saisie manuelle si le film est absent de TMDb. Avertissement de doublon. |
| **Tirage** | Les trois affiches en grand, pensé pour un écran de télévision ou un iPad. Heures de fin et plateformes visibles. Boutons *on prend celui-là* et *pas dans le mood, retire*. |
| **Séance en cours** | Le film retenu, saisie du veto le cas échéant, saisie des notes sur 5 après visionnage. |
| **Historique** | Timeline des séances : film, camp, dérogations, samedis sautés, notes. Compteur de vetos par personne. |
| **Palmarès** | Agrégats de la période et vue imprimable. |
| **Réglages** | Heure de démarrage par défaut, profils (nom, avatar, couleur), état de la clé TMDb. |

## 7. Architecture technique

Reprise du pattern éprouvé de Wishi, projet frère sur le même hébergement.

- **Backend** : PHP 8.1+, MVC maison, autoload PSR-4 via Composer.
- **Base de données** : SQLite, fichier unique `data/filmi.sqlite`, hors Git. `PRAGMA journal_mode=WAL` activé à l'initialisation et à chaque connexion, pour supporter deux ajouts simultanés sans verrou en écriture. Wishi ne l'active pas, Filmi le fait dès le départ.
- **Frontend** : Tailwind CSS et Alpine.js, sans étape de build.
- **API externe** : TMDb API v3, appelée **exclusivement côté serveur** pour ne jamais exposer la clé au navigateur.
- **PWA** : manifest et service worker, installable sur l'écran d'accueil iOS et Android.
- **Hébergement** : Infomaniak, sous-domaine `filmi.shi-ga.net`, docroot pointé sur `public/`.
- **Dépôt** : `~/Documents/Scripts/shigaepouyen/filmi`, dépôt Git dédié, distinct de Wishi. Le domaine métier n'a rien de commun et Wishi a sa propre trajectoire.

### 7.1 Arborescence

```
public/            # docroot
 ├─ index.php       # choix de profil
 ├─ tonight.php     # Ce samedi
 ├─ pool.php        # les deux pools
 ├─ add.php         # ajout d'un film
 ├─ draw.php        # tirage
 ├─ seance.php      # séance en cours
 ├─ history.php     # historique
 ├─ awards.php      # palmarès
 ├─ settings.php    # réglages
 ├─ api/            # endpoints JSON (search, vote, draw, rate...)
 ├─ assets/         # icônes, avatars SVG compilés
 ├─ manifest.json
 └─ sw.js

src/
 ├─ Controllers/    # un contrôleur par domaine (Movie, Vote, Seance, Profile, Awards)
 ├─ Services/       # TmdbService, DrawService, ScheduleService, AwardsService
 └─ Utils/          # Database, Security, Avatars, FormatUtils, Session

views/
 ├─ layouts/
 └─ components/     # movie_card, avatar_picker, poster_trio...

data/              # base SQLite, hors Git
config/            # config.php hors Git, config.example.php versionné
scripts/           # init_db.php, migrate.php
docs/superpowers/  # specs et plans
```

### 7.2 Découpage en unités

Chaque service a une responsabilité unique et testable en isolation :

- **TmdbService** : recherche, fiche détaillée, plateformes, certification. Seul point de contact avec l'API externe. Ne connaît rien de la base.
- **DrawService** : produit une shortlist depuis un ensemble de candidats. Fonction pure sur une liste en entrée, ce qui rend le quota, le cooldown et l'anti-répétition intra-soirée testables sans base de données.
- **ScheduleService** : détermine la date de la prochaine séance et le camp par défaut à partir de l'historique. Fonction pure sur la liste des séances.
- **AwardsService** : agrégats du palmarès sur une période.
- **Avatars** : catalogue des 24 avatars SVG et rendu avec la couleur du thème. Aucune dépendance.

Le cooldown et le quota vivant dans `DrawService` plutôt que dans une requête SQL, ils restent lisibles et vérifiables. Le service reçoit les candidats et l'historique des shortlists, il ne fait pas de SQL lui-même.

### 7.3 Schéma de données

```sql
PRAGMA journal_mode = WAL;

CREATE TABLE profiles (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    name     TEXT NOT NULL,
    slug     TEXT UNIQUE NOT NULL,
    side     TEXT NOT NULL CHECK (side IN ('adult','kid')),
    avatar   TEXT NOT NULL,           -- clé du catalogue d'avatars
    color    TEXT NOT NULL DEFAULT 'indigo'
);

CREATE TABLE movies (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    tmdb_id        INTEGER,
    title          TEXT NOT NULL,
    original_title TEXT,
    year           INTEGER,
    runtime        INTEGER,           -- minutes
    poster_url     TEXT,
    overview       TEXT,
    genres         TEXT,              -- JSON
    director       TEXT,
    tmdb_rating    REAL,
    certification  TEXT,              -- avis parental FR
    providers      TEXT,              -- JSON plateformes FR
    providers_at   DATETIME,          -- date du cache plateformes
    pool           TEXT NOT NULL CHECK (pool IN ('adult','kid')),
    bet_type       TEXT CHECK (bet_type IN ('safe','discovery')),
    memo           TEXT,
    added_by       INTEGER NOT NULL REFERENCES profiles(id),
    status         TEXT NOT NULL DEFAULT 'pool'
                   CHECK (status IN ('pool','watched','archived')),
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_movies_pool ON movies(pool, status);

CREATE TABLE votes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    movie_id   INTEGER NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (movie_id, profile_id)
);

CREATE TABLE seances (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    date            DATE NOT NULL UNIQUE,
    chooser_side    TEXT NOT NULL CHECK (chooser_side IN ('adult','kid')),
    derogation      INTEGER NOT NULL DEFAULT 0,
    derogation_note TEXT,
    status          TEXT NOT NULL DEFAULT 'planned'
                    CHECK (status IN ('planned','done','skipped')),
    movie_id        INTEGER REFERENCES movies(id) ON DELETE SET NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE seance_picks (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    seance_id     INTEGER NOT NULL REFERENCES seances(id) ON DELETE CASCADE,
    movie_id      INTEGER NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
    role          TEXT NOT NULL CHECK (role IN ('shortlist','chosen','vetoed')),
    by_profile_id INTEGER REFERENCES profiles(id),
    reason        TEXT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_picks_movie ON seance_picks(movie_id, role);

CREATE TABLE ratings (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    seance_id  INTEGER NOT NULL REFERENCES seances(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    score      INTEGER NOT NULL CHECK (score BETWEEN 1 AND 5),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (seance_id, profile_id)
);

CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);
-- default_start_time = '19:15'
```

Qui a proposé un film : `movies.added_by`. Qui a voté : `votes`. Qui a posé un veto : `seance_picks` avec `role = 'vetoed'`.

### 7.4 Flux de données du tirage

1. `draw.php` demande à `MovieRepository` les candidats du pool adulte en statut `pool`, et les `seance_picks` de rôle `shortlist` des **deux dernières séances qui possèdent une shortlist**. Les semaines filles n'en produisent pas et ne consomment donc pas de cooldown : deux tirages de cooldown signifient bien deux semaines parents, soit environ un mois de calendrier réel.
2. `DrawService::pick()` reçoit candidats, liste de cooldown et liste des films déjà montrés dans la session courante, et renvoie une shortlist de trois ou une erreur explicite de catégorie insuffisante.
3. La session PHP mémorise les films déjà montrés, pour l'anti-répétition des re-tirages. Rien n'est écrit en base.
4. À la validation du choix, une transaction unique écrit : la séance en `done`, les trois `seance_picks` en `shortlist`, le film retenu en `chosen`, et le passage du film en `watched`.

### 7.5 Gestion des erreurs

- **Clé TMDb absente ou invalide** : l'application démarre normalement, la recherche est désactivée, un bandeau l'indique, la saisie manuelle reste disponible. Aucune page blanche.
- **TMDb injoignable ou en quota** : l'autocomplete affiche un message et propose la saisie manuelle. Les données déjà en base ne dépendent pas de l'API.
- **Catégorie de pari insuffisante au tirage** : message nommant ce qui manque, avec un lien direct vers l'ajout de film. Pas de tirage dégradé silencieux.
- **Pool vide** : écran dédié plutôt qu'une liste vide.
- **Aucun profil sélectionné** : redirection vers le choix de profil, quelle que soit la page demandée.
- **Écriture concurrente SQLite** : WAL, plus un `busy_timeout` de cinq secondes.
- **Écritures** : protégées par jeton CSRF, comme dans Wishi.

### 7.6 Tests

Priorité à la logique métier, qui est là où les erreurs coûtent cher et où le test est facile parce que les services sont purs :

- `DrawService` : quota 1 safe + 2 discovery, respect du cooldown sur deux séances, anti-répétition intra-soirée, réinitialisation à épuisement, erreurs de catégorie insuffisante.
- `ScheduleService` : alternance normale, effet d'une dérogation sur le tour suivant, `skipped` qui reporte le camp sans le consommer, absence totale d'historique.
- `AwardsService` : agrégats sur un jeu de séances connu.
- `TmdbService` : parsing d'une réponse enregistrée, comportement sans clé, comportement en erreur réseau. Aucun appel réseau réel dans les tests.

Framework : PHPUnit via Composer. Une base SQLite en mémoire pour les tests touchant aux dépôts.

## 8. Identité visuelle

### 8.1 Avatars

Vingt-quatre avatars **SVG inline**, viewBox 96, style kawaii : silhouettes très rondes, grands yeux, joues rosées, deux couleurs plus un accent piloté par la couleur de thème du profil. SVG et non emoji, pour un rendu identique sur iPhone, Android et desktop, et sans asset externe.

**Contrainte de conception, non négociable** : ce sont des **archétypes de genre originaux**, pas des personnages identifiables. Pas de trait signature protégé (pas de sorcier à lunettes rondes et cicatrice, pas de sabre laser avec le costume qui va avec). Côté Kpop, pas de visage ressemblant à une personne réelle, aucun nom de groupe, aucun logo. Le folklore coréen est du domaine public et se dessine librement.

Catalogue par famille :

**Science-fiction** — 1 petit alien vert pâle, gros yeux en amande, deux antennes molles. 2 robot boîte de conserve, une antenne, chenille, un peu bosselé. 3 astronaute dans un casque bulle trop grand.

**Aventure** — 4 aventurière au chapeau de feutre, foulard, carte qui dépasse de la poche. 5 scaphandrier en cuivre, hublot rond, bulles. 6 aviatrice, casque de cuir et grosses lunettes relevées.

**Fantastique** — 7 apprenti sorcier, chapeau pointu étoilé, grimoire plus gros que lui. 8 gardien galactique en cape, bâton lumineux, masque simple. 9 petit dinosaure curieux, sourcils étonnés.

**Frissons** — 10 vampire, cape à col haut, deux crocs minuscules, sourire gêné. 11 traqueur de spectres, sac à dos qui fume, lampe torche. 12 créature poilue amicale, yéti timide qui fait signe.

**Classiques** — 13 détective en trench et chapeau mou, halo de lampadaire. 14 cow-boy en poncho, brin de paille, chapeau baissé.

**Corée, folklore et cinéma** — 15 gumiho, renarde à oreilles rondes et neuf petites queues en éventail. 16 dokkaebi, gobelin à corne unique, massue de bois, sourire malicieux. 17 haechi, lion-chien gardien de Séoul, crinière ronde. 18 jeune érudit en hanbok, chapeau de crin noir, éventail. 19 tigre du folklore, pataud et rayé, l'air de s'excuser. 20 zombie de bureau, cravate de travers, teint verdâtre.

**Kpop** — 21 idole au micro-casque, mèche de couleur, veste à épaulettes, pose finale de chorégraphie. 22 danseur en bomber oversize, bucket hat, grosses sneakers. 23 fan au lightstick, bandeau sur le front, halo lumineux animé en CSS. 24 trainee, sac de sport, bouteille d'eau, deux cernes attendrissants.

Le sélecteur les présente groupés par famille, avec un aperçu qui prend la couleur de thème choisie.

### 8.2 Icône de l'application

Un **seau de popcorn kawaii** sur fond dégradé chaud, avec le petit alien qui dépasse derrière en piochant dedans. Reconnaissable à 40 px sur un écran d'accueil et raccroché aux avatars.

Déclinaisons produites : `favicon.svg`, `favicon.ico` 32 px, `apple-touch-icon.png` 180 px, `icon-192.png` et `icon-512.png` pour le manifest.

## 9. Configuration et déploiement

`config/config.example.php` est versionné, `config/config.php` ne l'est pas :

```php
return [
    'tmdb_api_key' => '',        // clé v3, compte gratuit sur themoviedb.org
    'tmdb_language' => 'fr-FR',
    'tmdb_region' => 'FR',
    'db_path' => __DIR__ . '/../data/filmi.sqlite',
];
```

Le README documente la création du compte TMDb et l'obtention de la clé v3. L'application est utilisable sans clé, en saisie manuelle.

Déploiement : `lftp` ou `rsync` over SSH vers Infomaniak, `sites/filmi.shi-ga.net/`, docroot sur `public/`. Le dossier `data/` n'est jamais écrasé par un déploiement, il contient les vraies données de la famille. Sauvegarde = copie du fichier `.sqlite`.

## 10. Décisions et justifications

| Décision | Justification |
|---|---|
| SQLite plutôt qu'un fichier JSON | Deux ajouts simultanés en semaine écraseraient un JSON. Les agrégats de votes et de palmarès sont triviaux en SQL et pénibles à la main. |
| WAL dès l'initialisation | Écritures concurrentes attendues. Absent de Wishi, ajouté ici. |
| Dépôt séparé de Wishi | Domaine métier disjoint, cycles de vie indépendants. |
| Camp stocké et non recalculé | Les dérogations et les samedis sautés rendraient tout calcul à la volée faux. |
| `seances.date` en `UNIQUE` | Deux parents ouvrant l'application le même samedi soir depuis deux téléphones créeraient deux séances pour la même date, ce qui corromprait d'un coup l'alternance, la fenêtre de cooldown et le palmarès. C'est l'usage attendu, pas un cas limite. |
| Shortlists intermédiaires non persistées | Sinon les re-tirages consomment le cooldown et vident le pool. |
| Votes non pondérants sur le tirage | Le hasard est le sel du rituel. Les votes servent à trier et discuter. |
| Tirage limité au pool adulte | Les semaines filles, elles choisissent à la main. C'est la règle familiale existante. |
| Pas de PIN | La friction tuerait l'usage. Réversible plus tard, l'identité est déjà attachée à chaque action. |
| Avatars SVG plutôt qu'emoji | Rendu identique partout, couleur pilotée par le thème, aucun asset externe. |

## 11. Périmètre de la version 1

Tout ce qui précède est en version 1 : profils et avatars, deux pools, ajout TMDb et manuel, memo, upvotes, tag de pari, tirage 1 plus 2 avec cooldown et re-tirage, séances avec dérogation, samedi sauté et veto tracé, notes post-séance, heure de fin, plateformes de streaming, avis parental, alerte pool faible, historique, palmarès exportable, PWA et icônes.

## 12. Suite

Plan d'implémentation : `docs/superpowers/plans/2026-08-10-filmi.md`.
