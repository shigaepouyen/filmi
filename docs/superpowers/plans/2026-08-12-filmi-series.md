# Filmi v3, les séries

**Contexte critique** : l'application est **en production** avec les données réelles de la famille. Toute modification de schéma passe par une migration versionnée, jamais par une recréation. Sauvegardes dans `~/Documents/Scripts/shigaepouyen/filmi-backups/`.

## Le besoin

Les filles choisissent une série, par exemple Heartstopper. La famille va passer plusieurs samedis dessus, deux ou trois épisodes par soirée. Aujourd'hui une séance ne porte qu'un film et « vu » le retire définitivement : le modèle ne sait pas représenter une œuvre qui avance.

## Décisions prises

| Sujet | Décision |
|---|---|
| Alternance pendant une série | **Entrelacée** : la série avance les semaines du camp qui l'a choisie, l'autre camp garde ses samedis. Personne ne perd son tour. |
| Série dans le tirage des trois films | **Non**, le tirage reste réservé aux films. Tirer une série engagerait la famille sur des mois sans l'avoir décidé. |
| Notes | **Une seule note à la fin**, sur l'œuvre entière. |
| Épisodes par soirée | **Réglable par série**, 2 par défaut. Deux épisodes de 30 minutes et deux de 55 ne font pas la même soirée. |
| Franchir une fin de saison | **Autorisé** : une soirée peut terminer une saison et enchaîner sur la suivante. |

## Le modèle : une suite continue d'épisodes

Puisqu'une soirée peut chevaucher deux saisons, les épisodes sont numérotés en **une seule suite** de 1 à N, toutes saisons confondues. La progression est alors un simple compteur d'épisodes vus, et une soirée prend les `n` suivants sans se soucier des frontières de saison.

Chaque épisode de la suite garde sa saison, son numéro dans la saison, sa durée et son titre, ce qui permet d'afficher « S1E8 à S2E1 » et de calculer l'heure de fin exactement.

Vérifié sur l'API réelle : `episode_run_time` au niveau série est **vide** pour Heartstopper, alors que l'endpoint saison donne la durée de chaque épisode (27, 33, 30, 29 minutes). La suite doit donc être construite depuis les endpoints saison, un appel par saison.

## Migration v4

Sur `movies` :

| Colonne | Rôle |
|---|---|
| `kind` | `film` par défaut, ou `series` |
| `season_count`, `episode_count` | totaux, pour afficher l'ampleur de l'engagement |
| `episodes_per_evening` | 2 par défaut, réglable par série |
| `episodes_watched` | progression, index dans la suite continue |
| `episodes` | JSON de la suite : saison, numéro, durée, titre |

Sur `seances` :

| Colonne | Rôle |
|---|---|
| `episodes_from`, `episodes_to` | bornes dans la suite continue, incluses |
| `episodes_label` | libellé figé, par exemple `S1E3 à S1E4`, pour que l'historique reste juste même si la suite est rafraîchie plus tard |

**Une série en cours reste en statut `pool`** et n'en sort qu'au dernier épisode. C'est volontaire : la contrainte `CHECK` sur `status` ne peut pas être modifiée par `ALTER TABLE`, et reconstruire la table sur des données réelles serait un risque inutile. Une série en cours se reconnaît à `episodes_watched > 0`.

## Ce qui est construit

### Logique

| Unité | Rôle |
|---|---|
| `SeriesService` (pur) | Calcule la soirée : plage d'épisodes, durée totale, libellé, et si la série se termine ce soir. Aucune I/O. |
| `TmdbService::searchSeries()` | `/search/tv`, mêmes garanties que la recherche de films |
| `TmdbService::seriesDetails()` | `/tv/{id}` plus un appel par saison, construit la suite continue |
| `MovieRepository::addSeries()` | ou `add()` étendu, avec `kind` et les champs de série |
| `MovieRepository::advanceSeries()` | avance la progression, passe en `watched` au dernier épisode |
| `MovieRepository::setEpisodesPerEvening()` | réglage par série |
| `MovieRepository::drawCandidates()` | **filtre `kind = 'film'`**, les séries ne sortent jamais au tirage |
| `SeanceRepository::recordSeriesEvening()` | enregistre la séance avec sa plage, avance la série, transactionnel |
| `AwardsService::compute()` | compte les **œuvres distinctes** et non les séances |

### Le comptage du palmarès, à ne pas rater

Le palmarès compte aujourd'hui les séances. Une série sur douze samedis afficherait douze œuvres vues. Il doit compter les `movie_id` distincts. L'historique, lui, garde une ligne par samedi, c'est ce qu'on veut y lire.

### Écrans

| Écran | Modification |
|---|---|
| `tonight.php` | Une série en cours propose « On continue », avec la plage d'épisodes, la durée et l'heure de fin. Bouton pour choisir autre chose. |
| `add.php` | Bascule film ou série dans la recherche, et le réglage des épisodes par soirée |
| `movie.php` | Fiche série : saisons, épisodes, progression, réglage des épisodes par soirée, et la reprise |
| `pool.php` | Une série affiche sa progression, par exemple « série, 4 épisodes sur 24 » |
| `history.php` | Chaque samedi de série affiche sa plage d'épisodes |
| `seance.php` | La note n'est proposée qu'au dernier épisode |

## Tâches

1. **Migration, TMDb séries, SeriesService, dépôts, palmarès.** Logique et tests, aucune vue.
2. **Les écrans.**
3. **Migration de la production**, faite par le contrôleur : sauvegarde, déploiement, migration, vérification que les 18 films et les réglages sont intacts.
