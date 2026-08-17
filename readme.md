# Filmi

Filmi est le mémo de films et le tirage du samedi soir d'une famille de quatre : JC et Élodie, Zoé et Soline.

## Le problème que ça résout

Tous les samedis soirs, c'est pizza, apéro et un film. Le choix alterne : une semaine les filles décident, la semaine suivante les parents proposent trois films et les filles tranchent parmi ces trois.

Le vrai problème n'a jamais été de choisir, il a toujours été de **produire la liste au moment voulu**. Les idées arrivent en semaine, « tiens, il faudrait leur montrer ça », et elles sont oubliées le samedi venu. Personne n'arrive à sortir trois titres sur le moment.

Filmi est donc d'abord un carnet d'idées partagé, et seulement ensuite une machine à tirer au sort. Si vous ne deviez retenir qu'un écran, c'est celui d'ajout d'un film : tout le reste existe pour servir ce qui s'y écrit.

## Le rituel, tel qu'il est

- **Semaine des filles** : elles choisissent le film. Les parents disposent d'un droit de veto, rarement utilisé.
- **Semaine des parents** : trois films proposés, en visant une valeur sûre et deux découvertes. Les filles tranchent.
- Un film vu ne revient pas dans les propositions, sauf s'il est explicitement remis en liste pour être revu.
- Une série se regarde sur plusieurs samedis, deux ou trois épisodes par soirée.
- **L'alternance n'est pas rigide.** Le tour peut être inversé par dérogation, et un samedi sans ciné reporte le tour prévu au lieu de le brûler.

## Fonctionnalités

### Les deux listes et le mémo

Deux listes d'idées, une alimentée par les parents, une par les filles, toutes deux visibles de tout le monde. Chaque film indique qui l'a proposé et porte un **mémo libre** : pourquoi je veux leur montrer ça. Ce champ est le cœur de l'outil, pas une décoration : trois semaines plus tard, c'est lui qui rappelle l'intention derrière un titre.

Une alerte de doublon prévient si le film est déjà dans une liste ou déjà vu, avec sa date. Elle informe, elle ne bloque jamais l'enregistrement.

### Votes

Un upvote par personne et par film, en bascule, dans les deux listes. Le compteur est visible et la liste des votants consultable. Les votes servent à trier et à discuter, **ils ne pondèrent pas le tirage** : le hasard est le sel du rituel.

### Le tirage

Sur la liste des parents uniquement. Chaque film y est tagué valeur sûre ou découverte, et le tirage sort **une valeur sûre puis deux découvertes**, au hasard dans chaque catégorie.

Deux garde-fous :

- **Cooldown** : un film proposé mais non retenu est écarté des deux tirages suivants. Les semaines des filles ne produisent pas de shortlist et ne consomment donc pas de créneau : deux tirages de cooldown, c'est environ un mois de calendrier réel.
- **Re-tirage illimité** : le bouton « pas dans le mood » régénère la sélection autant de fois que voulu. Les shortlists intermédiaires ne sont **jamais** enregistrées, sinon trois re-tirages un samedi soir grilleraient la moitié de la liste.

**Les suites attendent leur tour.** Un film qui appartient à une saga connue de TMDb n'est pas proposé tant qu'un épisode antérieur de la même saga est encore en liste sans avoir été vu : « Violent Night 2 » ne sort pas avant « Violent Night ». Un rang antérieur absent des listes ne bloque rien, on suppose alors qu'il a été vu ailleurs. La fiche du film porte un interrupteur « ignorer l'ordre » pour les sagas où l'ordre est indifférent, et un film remis en liste pour être revu échappe à la règle.

Si une catégorie ne peut pas être servie, l'application le dit clairement et propose d'ajouter un film, plutôt que de compléter en silence.

Sur l'écran des trois films tirés, chaque affiche ouvre une fenêtre de détail : synopsis, durée, heure de fin, plateformes, bande-annonce jouable sur place, et un lien vers la fiche complète. On tranche sans quitter le tirage.

### La séance

Les semaines des filles, elles choisissent directement dans leur liste. Un parent peut poser un **veto tracé** : auteur, film, raison, et un compteur par personne visible dans l'historique. Le but est l'auto-régulation, le jour où quelqu'un abuse tout le monde le voit. Le film veto retourne dans la liste, il n'est pas consommé.

Après le film, chacun met une note sur 5, depuis la page de la séance ou directement dans l'historique. Ces notes alimentent l'historique et le palmarès, sans aucun effet sur les tirages suivants.

Une note déjà donnée ne se remplace pas d'un doigt qui dérape : changer d'avis demande une confirmation. Et **qui n'a pas vu le film ferme sa ligne** d'une croix, ce qui la sort de ses séances à noter. Un tour passé n'est pas une note : il ne pèse ni sur la moyenne ni sur le palmarès, et il se rouvre à tout moment par « Noter quand même ». La séance affiche qui a noté et qui ne notera pas, pour savoir quand elle n'attend plus personne.

Une série ne se note **qu'une fois**, à son dernier épisode, sur l'œuvre entière. Les soirées intermédiaires n'ouvrent pas de ligne de note.

### Les séries

Une série s'ajoute comme un film, avec son nombre total d'épisodes récupéré chez TMDb et un **nombre d'épisodes par soirée réglable série par série** : deux pour l'une, trois pour l'autre. Chaque samedi consacré à la série avance la progression et l'historique retient la plage exacte, « épisodes 3 à 5 ». Quand une soirée déborde sur la saison suivante, les deux saisons s'enchaînent dans la même soirée.

La série sort des listes une fois le dernier épisode vu. Un veto sur une soirée de série rembobine la progression : les épisodes non regardés ne restent pas comptés vus.

### Revoir, et rattraper l'historique

**Revoir** : depuis la fiche, un film déjà vu retourne dans sa liste pour un second passage. Il redevient tirable, garde toutes ses visions passées, et n'est pas soumis à la règle des suites puisque la saga a déjà été suivie.

**Rattraper l'historique** : un film vu avant l'installation de l'application s'ajoute avec un « déjà vu le » à une date passée, sur la liste des parents ou des filles selon qui l'avait choisi. Ces séances de rattrapage nourrissent l'historique et le palmarès, mais **ne comptent pas dans l'alternance** : elles ne décalent pas le tour du samedi suivant.

Le « vu le » d'un film se corrige : changer sa date, ou le retirer, ce qui remet l'œuvre dans sa liste.

### La fiche d'un film

Chaque film a sa page : grande affiche, synopsis complet, réalisateur, genres, note TMDb, avis parental, durée et heure de fin, plateformes, lien vers la bande-annonce quand TMDb en connaît une, mémo, proposeur et votants. La liste affiche le synopsis tronqué et mène à cette fiche.

Depuis la fiche, on peut **changer le pari, la liste et le proposeur** d'un film, corriger son « vu le », le remettre en liste pour le revoir, et **l'archiver**. On y accède depuis la liste, depuis le tirage et depuis n'importe quelle séance de l'historique. Un archivage est réversible : le film disparaît des listes, du tirage et de la détection de doublon, mais rien n'est effacé, donc l'historique et le palmarès gardent leur trace s'il avait déjà été proposé ou veto.

**Qui agit sur quoi**, et la règle est volontairement asymétrique : les parents alimentent et gèrent **les deux listes**, y compris celle des filles, alors que les filles ne gèrent que la leur. En revanche tout le monde voit les deux listes et peut voter partout, c'est ce qui permet aux filles de découvrir ce que les parents ont en réserve et de faire remonter leurs préférences.

La règle est vérifiée **côté serveur**, pas seulement par un bouton masqué, et elle couvre l'ajout, le changement de liste et l'archivage. Une requête forgée qui viserait la liste des parents depuis un profil enfant est refusée, et la liste demandée n'est jamais ramenée en silence vers une autre : mieux vaut un refus explicite qu'un film écrit ailleurs que là où on le demandait.

### Les abonnements

TMDb renvoie, à côté des abonnements, toutes les boutiques de location et d'achat, ce qui fait jusqu'à vingt plateformes pour un seul film. Les réglages proposent donc de **cocher les plateformes auxquelles la famille est abonnée**, avec leur logo, regroupées par marque : « Netflix » et « Netflix Standard with Ads » sont la même chose, et n'apparaissent qu'une fois.

Une fois ce périmètre défini, la liste et le tirage n'affichent plus que les plateformes réellement accessibles, soit quatre ou cinq au maximum. Un film dont aucune plateforme n'est dans le périmètre porte un simple avertissement « hors abonnement », jamais un blocage : rien n'empêche de le regarder autrement.

Tant que rien n'est coché, toutes les plateformes s'affichent. C'est l'état transitoire avant le premier réglage.

### La mise à jour des fiches

Les plateformes changent, les bandes-annonces arrivent après la sortie. Les réglages portent un bouton **« Mettre à jour maintenant »** qui repasse sur les œuvres encore en liste, avec une barre de progression et le détail des échecs. Il récupère les plateformes, l'avis parental, la bande-annonce et la saga. La progression d'une série en cours n'est jamais touchée.

Le même travail peut tourner en tâche planifiée hebdomadaire, mais le bouton suffit à une famille.

### Les informations qui font trancher un samedi soir

- **Heure de fin estimée** sur chaque film, calculée depuis une heure de démarrage configurable, 19:15 par défaut. Avec des enfants, c'est souvent le critère décisif.
- **Plateformes de streaming** disponibles en France, pour ne pas tirer à 19h00 un film introuvable.
- **Avis parental** français en pastille, utile vu l'écart d'âge entre les filles.

### Le reste

- **Alerte de liste faible** dès que la liste des parents passe sous cinq films, avec le détail par catégorie. C'est le rappel préventif qui manquait, en semaine et pas dans la panique du samedi.
- **Historique** de chaque samedi : film retenu, camp, dérogations, samedis sautés, notes, vetos. Chaque ligne mène à sa séance et se note sur place.
- **Palmarès** annuel avec une page imprimable autonome : podium, portraits de la saison, anecdotes, qui a choisi quoi.
- **20 avatars façon borne d'arcade**, silhouettes monochromes en SVG inline, à la couleur du profil.
- **PWA installable** sur l'écran d'accueil des iPhone.

## Installation

Prérequis : PHP 8.1 ou plus avec `pdo_sqlite`, `curl` et `json`, plus Composer.

```bash
composer install
mkdir -p data
cp config/config.example.php config/config.php
php scripts/init_db.php
php -S localhost:8000 -t public
```

`init_db.php` crée la base et les quatre profils. Il est idempotent, on peut le relancer sans risque.

Les tests :

```bash
composer test
```

## Obtenir une clé TMDb

TMDb fournit les affiches, les durées, les plateformes et l'avis parental. IMDb n'a pas d'API publique gratuite, c'est pour cela que Filmi utilise TMDb.

1. Créer un compte gratuit sur themoviedb.org.
2. Aller dans Paramètres, puis API, et demander une clé **v3**.
3. L'enregistrer avec le script prévu pour ça, plutôt qu'en éditant le fichier à la main :

```bash
./scripts/install_hooks.sh   # une seule fois, après un clone
./scripts/set_tmdb_key.sh
```

Le script demande la clé en **saisie masquée** : elle ne s'affiche pas, elle n'entre pas dans l'historique du shell, et elle n'apparaît pas dans la liste des processus puisqu'elle est transmise à PHP par l'environnement et non par un argument. Il crée `config/config.php` en permissions 600, puis vérifie que git l'ignore bien et affiche une empreinte tronquée pour confirmer sans révéler la clé.

`install_hooks.sh` pose un hook `pre-commit` qui refuse tout commit contenant `config/config.php`, un fichier `.sqlite`, ou une clé TMDb non vide dans n'importe quel fichier indexé, y compris ajouté de force avec `git add -f`. Les hooks ne se versionnent pas, donc ce script est à relancer après chaque clone.

**Filmi fonctionne sans clé**, en saisie manuelle, avec un bandeau d'avertissement en haut de page. La recherche autocomplétée est alors désactivée, tout le reste marche.

La clé **ne quitte jamais le serveur** : tous les appels à TMDb passent par `src/Services/TmdbService.php`, et le navigateur ne reçoit que les résultats.

## Stack technique

PHP 8.1 en MVC maison, sans framework. SQLite en fichier unique avec le mode WAL. Tailwind CSS et Alpine.js par CDN, sans étape de build. PHPUnit pour les tests. API TMDb v3. Inkscape pour rastériser les icônes.

**Pourquoi SQLite et pas un simple fichier JSON**, puisque le besoin disait « un fichier sur le serveur » : deux personnes qui ajoutent une idée le même soir écraseraient un JSON, et les agrégats de votes et de palmarès sont triviaux en SQL alors qu'ils seraient pénibles à la main. La base reste un seul fichier à sauvegarder, l'esprit du besoin est respecté.

Le mode WAL est activé dès l'initialisation, précisément pour supporter ces écritures concurrentes.

## Structure du projet

```
public/            # docroot
 ├─ index.php       # choix de profil
 ├─ tonight.php     # Ce samedi
 ├─ pool.php        # les deux listes
 ├─ add.php         # ajout d'un film
 ├─ draw.php        # tirage
 ├─ seance.php      # séance en cours, veto, notes
 ├─ movie.php       # fiche d'une œuvre
 ├─ history.php     # historique
 ├─ awards.php      # palmarès
 ├─ settings.php    # réglages
 ├─ api/            # endpoints JSON (search, duplicate, vote, draw,
 │                  #   rate, rate_skip, refresh)
 └─ assets/icons/   # favicon et icônes PWA

src/
 ├─ App.php         # amorçage, rendu, garde CSRF
 ├─ Services/       # logique pure et accès réseau
 ├─ Repositories/   # seule couche SQL
 └─ Utils/          # base, config, session, avatars, formatage

views/
 ├─ layouts/        # coque HTML commune
 └─ components/     # card de film, sélecteur d'avatar

data/              # base SQLite, hors Git
config/            # config.php hors Git, config.example.php versionné
scripts/           # init_db, build_icons, refresh_providers, deploy
tests/             # PHPUnit
```

**Le découpage qui compte** : les services purs (`DrawService`, `ScheduleService`, `AwardsService`, `SeriesService`, `SequelService`, `FormatUtils`) ne touchent jamais la base, les dépôts sont la seule couche qui parle SQL, et `TmdbService` est la seule classe qui parle au réseau, avec un transport injectable.

C'est ce qui rend les règles du tirage et de l'alternance testables sans base de données, et c'est pour ça que **la suite de tests ne fait aucun appel réseau**.

## Tests

```bash
composer test
```

Couvert : le tirage (quota, cooldown, anti-répétition, échecs), l'alternance (dérogation, samedi sauté, rattrapage), l'ordre des sagas, la progression des séries, les agrégats du palmarès, le formatage des durées et dates, les migrations rejouées sur d'anciens états de base, le client TMDb sur des fixtures enregistrées, les dépôts sur une base en mémoire, et le rendu de quelques vues.

Non couvert par des tests automatiques : les pages elles-mêmes, vérifiées manuellement en HTTP pendant le développement.

## Avatars

20 silhouettes façon borne d'arcade, réparties en quatre familles : envahisseurs, vaisseaux, machines, créatures.

Chaque sprite est une grille de caractères 16x16, convertie en rectangles SVG sans lissage. Le corps entier prend **la couleur du profil** : les bornes des années 80 étaient monochromes, et cette contrainte tombe bien, puisque quatre personnes deviennent quatre couleurs reconnaissables de l'autre bout du canapé. Seul le détail sombre, les yeux ou le cockpit, garde la teinte du fond.

**Ce qui distingue les vingt n'est pas le détail mais l'emprise au sol** : large et plate pour le crabe, étroite et haute pour la méduse, en croix pour l'intercepteur, en flèche pour le chasseur. C'est ce qui les sépare encore à 36 pixels dans l'en-tête, là où un dessin détaillé redevient une tache. Un roster de personnages fouillés a été essayé avant celui-ci et abandonné pour cette raison.

Les sprites sont symétriques par construction : le script de dessin n'en décrit que la moitié gauche et la reflète.

**Règle de conception, non négociable** : ce sont des formes originales. Le vocabulaire graphique de l'arcade est emprunté, jamais les dessins de Taito ou de Namco, qui restent leur propriété. Aucun personnage identifiable, aucun logo.

Pour les visualiser :

```bash
php scripts/preview_avatars.php
```

## Icônes

```bash
./scripts/build_icons.sh
```

Nécessite Inkscape (`brew install inkscape`). Produit le favicon 32 px, l'icône iOS 180 px et les icônes 192 et 512 px du manifest à partir des deux sources SVG.

## Déploiement

```bash
./scripts/deploy.sh
```

Le script lance la suite de tests et refuse de partir si elle échoue, puis synchronise vers Infomaniak. Le docroot doit pointer sur `public/`.

**Le script ne touche jamais `data/` ni `config/config.php`.** `data/` contient les vraies données de la famille et `config/config.php` la clé TMDb.

Sauvegarde : copier le fichier `data/filmi.sqlite`. C'est tout.

### Migrations de schéma

`CREATE TABLE IF NOT EXISTS` crée les tables manquantes mais **ne modifie jamais une table existante**, donc une colonne ajoutée plus tard n'arriverait pas en production par ce chemin. C'est pour ça qu'il existe un mécanisme de migration versionné :

```bash
php scripts/migrate.php
```

La version courante vit dans `settings.schema_version`, son absence signifiant la version 1. Chaque migration s'applique dans sa propre transaction, vérifie l'état réel de la base avant d'agir, et ne se rejoue jamais. `init_db.php` les applique à la fin, donc un déploiement les passe automatiquement.

**Avant toute migration sur la production, prendre une sauvegarde et répéter la migration sur cette copie.** La procédure :

```bash
ssh infomaniak-prod 'cd sites/filmi.shi-ga.net && php -r "require \"vendor/autoload.php\"; App\Utils\Database::connect()->exec(\"VACUUM INTO '\''/tmp/sauvegarde.sqlite'\''\");"'
```

`VACUUM INTO` produit un instantané cohérent même si quelqu'un utilise l'application au même moment, contrairement à une simple copie du fichier.

Pour travailler sur cette copie en local sans jamais toucher au `config/config.php` réel, la variable d'environnement `FILMI_CONFIG` pointe une autre configuration :

```bash
FILMI_CONFIG=/tmp/ma-copie/config.php php scripts/migrate.php
```

### Rafraîchissement des plateformes

Le bouton des réglages suffit au quotidien. Pour automatiser, le même travail tourne en ligne de commande avec `scripts/refresh_providers.php`, à planifier une fois par semaine côté Infomaniak :

L'hébergement mutualisé Infomaniak n'expose pas `crontab`, la tâche se crée donc dans le Manager, rubrique Hébergement puis Tâches cron. Commande exacte, avec les chemins absolus de ce compte :

```
/opt/php8.4/bin/php /home/clients/a1d3f2277a515800e7b154e91c8a9174/sites/filmi.shi-ga.net/scripts/refresh_providers.php
```

Une fois par semaine suffit, par exemple le jeudi matin, pour que les plateformes soient à jour avant le samedi.

Le script traite au maximum 25 films par exécution, les jamais interrogés d'abord puis les plus périmés, de sorte qu'une exécution interrompue reprend où elle s'est arrêtée. Il est sans effet en l'absence de clé TMDb, et il n'est **jamais** appelé pendant le rendu d'une page : un appel réseau bloquant ferait attendre l'utilisateur pour une information qui bouge lentement.

## Réglages

Depuis la page Réglages : le nom, l'avatar et la couleur de chaque profil, l'heure de démarrage du film qui sert au calcul des heures de fin, les plateformes auxquelles la famille est abonnée, et la mise à jour des fiches.

Le seuil de l'alerte de liste faible se règle dans `config/config.php`, champ `low_pool_threshold`.
