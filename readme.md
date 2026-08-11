# Filmi

Filmi est le mémo de films et le tirage du samedi soir d'une famille de quatre : JC et Élodie, Zoé et Soline.

## Le problème que ça résout

Tous les samedis soirs, c'est pizza, apéro et un film. Le choix alterne : une semaine les filles décident, la semaine suivante les parents proposent trois films et les filles tranchent parmi ces trois.

Le vrai problème n'a jamais été de choisir, il a toujours été de **produire la liste au moment voulu**. Les idées arrivent en semaine, « tiens, il faudrait leur montrer ça », et elles sont oubliées le samedi venu. Personne n'arrive à sortir trois titres sur le moment.

Filmi est donc d'abord un carnet d'idées partagé, et seulement ensuite une machine à tirer au sort. Si vous ne deviez retenir qu'un écran, c'est celui d'ajout d'un film : tout le reste existe pour servir ce qui s'y écrit.

## Le rituel, tel qu'il est

- **Semaine des filles** : elles choisissent le film. Les parents disposent d'un droit de veto, rarement utilisé.
- **Semaine des parents** : trois films proposés, en visant une valeur sûre et deux découvertes. Les filles tranchent.
- Un film vu ne revient pas dans les propositions.
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

Si une catégorie ne peut pas être servie, l'application le dit clairement et propose d'ajouter un film, plutôt que de compléter en silence.

### La séance

Les semaines des filles, elles choisissent directement dans leur liste. Un parent peut poser un **veto tracé** : auteur, film, raison, et un compteur par personne visible dans l'historique. Le but est l'auto-régulation, le jour où quelqu'un abuse tout le monde le voit. Le film veto retourne dans la liste, il n'est pas consommé.

Après le film, chacun met une note sur 5. Ces notes alimentent l'historique et le palmarès, sans aucun effet sur les tirages suivants.

### Les informations qui font trancher un samedi soir

- **Heure de fin estimée** sur chaque film, calculée depuis une heure de démarrage configurable, 19:15 par défaut. Avec des enfants, c'est souvent le critère décisif.
- **Plateformes de streaming** disponibles en France, pour ne pas tirer à 19h00 un film introuvable.
- **Avis parental** français en pastille, utile vu l'écart d'âge entre les filles.

### Le reste

- **Alerte de liste faible** dès que la liste des parents passe sous cinq films, avec le détail par catégorie. C'est le rappel préventif qui manquait, en semaine et pas dans la panique du samedi.
- **Historique** de chaque samedi : film retenu, camp, dérogations, samedis sautés, notes, vetos.
- **Palmarès** annuel avec une page imprimable autonome.
- **24 avatars kawaii** en SVG inline, sur des archétypes de cinéma originaux.
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
 ├─ history.php     # historique
 ├─ awards.php      # palmarès
 ├─ settings.php    # réglages
 ├─ api/            # endpoints JSON (search, duplicate, vote, draw)
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

**Le découpage qui compte** : les services purs (`DrawService`, `ScheduleService`, `AwardsService`, `FormatUtils`) ne touchent jamais la base, les dépôts sont la seule couche qui parle SQL, et `TmdbService` est la seule classe qui parle au réseau, avec un transport injectable.

C'est ce qui rend les règles du tirage et de l'alternance testables sans base de données, et c'est pour ça que **la suite de tests ne fait aucun appel réseau**.

## Tests

```bash
composer test
```

Couvert : le tirage (quota, cooldown, anti-répétition, échecs), l'alternance (dérogation, samedi sauté), les agrégats du palmarès, le formatage des durées et dates, le client TMDb sur des fixtures enregistrées, les cinq dépôts sur une base en mémoire, et deux composants de vue.

Non couvert par des tests automatiques : les pages elles-mêmes, vérifiées manuellement en HTTP pendant le développement.

## Avatars

24 archétypes répartis en sept familles : science-fiction, aventure, fantastique, frissons, classiques, Corée et Kpop.

**Règle de conception, non négociable** : ce sont des archétypes de genre **originaux**, jamais des personnages identifiables. Aucun trait signature protégé, aucun visage ressemblant à une personne réelle, aucun nom de groupe, aucun logo. Le folklore coréen est du domaine public et se dessine librement.

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

**Limite connue sur le schéma** : `init_db.php` utilise `CREATE TABLE IF NOT EXISTS`, ce qui crée les tables manquantes mais **ne modifie jamais une table existante**. Une colonne ajoutée plus tard n'arrivera donc pas en production par ce chemin. Le jour où le schéma évolue après la mise en ligne, il faudra une vraie migration versionnée.

### Rafraîchissement des plateformes

Le cache des plateformes de streaming est mis à jour par `scripts/refresh_providers.php`, à planifier une fois par semaine côté Infomaniak :

```
php /chemin/vers/filmi/scripts/refresh_providers.php
```

Le script traite au maximum 25 films par exécution, les jamais interrogés d'abord puis les plus périmés, de sorte qu'une exécution interrompue reprend où elle s'est arrêtée. Il est sans effet en l'absence de clé TMDb, et il n'est **jamais** appelé pendant le rendu d'une page : un appel réseau bloquant ferait attendre l'utilisateur pour une information qui bouge lentement.

## Réglages

Depuis la page Réglages : le nom, l'avatar et la couleur de chaque profil, et l'heure de démarrage du film qui sert au calcul des heures de fin.

Le seuil de l'alerte de liste faible se règle dans `config/config.php`, champ `low_pool_threshold`.
