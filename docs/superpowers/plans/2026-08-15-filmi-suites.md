# Filmi v5, les suites

**Contexte critique** : l'application est **en production** avec les données réelles de la famille, 22 films, un roster d'avatars pixel art et le schéma en version 6. Toute modification de schéma passe par une migration versionnée. Sauvegardes dans `~/Documents/Scripts/shigaepouyen/filmi-backups/`.

## Le besoin

Ne pas se faire proposer Violent Night 2 alors que Violent Night est encore dans la liste, jamais vu. Aujourd'hui rien ne relie deux films d'une même saga et le tirage peut parfaitement sortir la suite avant l'original.

## Décisions prises

| Sujet | Décision |
|---|---|
| Détection | **Automatique via les collections TMDb.** Vérifié sur l'API réelle : « Violent Night - Saga » regroupe les deux films avec leurs dates de sortie, ce qui donne l'ordre. |
| Portée du blocage | **Tirage et choix à la main.** Une suite bloquée ne sort pas au tirage des parents et les filles ne peuvent pas la choisir non plus. |
| Condition | Le blocage ne s'applique **que si le précédent est présent dans une liste et pas encore vu**. Si le précédent n'a jamais été ajouté, on suppose qu'il a été vu avant Filmi et on ne bloque pas. |
| Listes | Le blocage joue **entre les deux listes** : l'ordre de visionnage est une propriété de la famille, pas d'une liste. Un précédent dans la liste des parents bloque une suite dans celle des filles. |
| Archivé | Un précédent archivé **ne bloque pas** : il est sorti du jeu. |
| Visibilité | Une suite bloquée reste **visible** dans la liste, avec la mention de ce qu'il faut voir d'abord. Cacher le film rendrait le comportement incompréhensible. |

## Ce que l'API donne vraiment, vérifié

Testé sur l'API réelle, l'ordre par date de sortie est le bon ordre de visionnage pour : Violent Night (2 films), John Wick (4), Avatar (5), Harry Potter (8), Le Seigneur des anneaux (3), Shrek (5), Terminator (6).

**Sauf Star Wars**, et c'est la limite à connaître : par date de sortie la saga donne la trilogie originale, puis les épisodes I à III, puis la postlogie. Ajouter l'épisode I et l'épisode IV ferait dire à l'application « voir d'abord l'épisode IV ». C'est l'ordre de sortie, ce n'est pas l'ordre chronologique.

D'où une **échappatoire par film** : `ignore_order`, une case sur la fiche du film, qui neutralise le blocage pour ce film précis. Sans elle l'application aurait tort sans recours possible.

Note au passage : les collections contiennent les films **pas encore sortis**, Avatar 4 en 2029, Shrek 5 en 2027. Sans effet ici, puisqu'un film ne bloque que s'il est présent dans une liste.

## Le modèle

TMDb expose `belongs_to_collection` sur la fiche d'un film : identifiant et nom de la saga. L'endpoint `/collection/{id}` liste les parts avec leur date de sortie, ce qui donne l'ordre.

On stocke sur le film :

| Colonne | Rôle |
|---|---|
| `collection_id` | identifiant TMDb de la saga, nul si le film n'en fait pas partie |
| `collection_name` | nom de la saga, pour l'affichage |
| `collection_rank` | rang dans la saga, calculé depuis les dates de sortie à l'ajout |
| `ignore_order` | à 1, ce film n'est jamais bloqué : échappatoire pour les sagas dont l'ordre de sortie n'est pas l'ordre de visionnage |

Le rang est figé à l'ajout plutôt que recalculé : il ne dépend que des dates de sortie, qui ne bougent pas, et cela évite un appel réseau à chaque tirage.

**Une œuvre sans `collection_id` n'est jamais bloquée ni bloquante.** C'est le cas des films saisis à la main sans TMDb, et c'est assumé.

## La règle, isolée et testable

`SequelService::blockedBy(array $movie, array $catalogue): ?array` : rend le film qui bloque, ou `null`.

Un film est bloqué s'il existe dans le catalogue un film de la **même collection**, de **rang strictement inférieur**, dont le statut est `pool`. Le catalogue passé en argument contient les films des deux listes, tous statuts confondus, ce qui rend le service pur et testable sans base.

Quand plusieurs précédents manquent, c'est **le plus ancien non vu** qui est nommé : c'est celui par lequel il faut commencer.

## Migration v7

Quatre colonnes sur `movies` : `collection_id INTEGER`, `collection_name TEXT`, `collection_rank INTEGER`, `ignore_order INTEGER NOT NULL DEFAULT 0`.

Les 22 films existants restent à `NULL`, donc non bloquants et non bloqués, ce qui est le comportement actuel. Un rafraîchissement ultérieur pourra les renseigner, ce n'est pas indispensable au premier jour.

## Ce qui est construit

### Logique

| Unité | Rôle |
|---|---|
| `Migrations` migration 7 | Les trois colonnes |
| `SequelService::blockedBy()` | La règle, pure |
| `TmdbService::details()` | Renvoie en plus la collection et le rang, via un appel `/collection/{id}` quand le film en a une |
| `MovieRepository::add()` | Persiste les trois champs |
| `MovieRepository::drawCandidates()` | **Écarte les films bloqués** |
| `MovieRepository::pool()` | Expose de quoi afficher le blocage sur la carte |
| `refresh_providers.php` | Renseigne aussi la collection des films déjà en base, qui sont tous à `NULL` |

### Écrans

| Écran | Modification |
|---|---|
| `views/components/movie_card.php` | Une suite bloquée porte la mention « à voir après Violent Night » et son bouton de choix du soir est retiré |
| `public/pool.php` | Refuse le choix d'un film bloqué, **côté serveur** |
| `views/movie.php` | La fiche indique la saga, le rang, et ce qu'il faut voir d'abord |
| `views/draw.php` | Rien de particulier : les films bloqués n'arrivent jamais jusqu'au tirage |

## Le piège à ne pas rater

`DrawService` lève déjà une exception quand une catégorie ne peut pas être servie. Avec des suites écartées, ce cas devient plus fréquent, et le message doit rester compréhensible : il doit dire qu'il manque une valeur sûre ou une découverte, pas laisser croire que la liste est vide alors que des films sont simplement retenus par leur ordre de visionnage.

## Vérifications attendues

- Deux films d'une même saga, le premier en `pool` : le second est absent du tirage et son choix à la main est refusé côté serveur.
- Le premier passe en `watched` : le second redevient tirable et choisissable, sans autre action.
- Le premier est archivé : le second n'est pas bloqué.
- Le premier n'est pas dans la liste du tout : le second n'est pas bloqué.
- Un précédent dans l'autre liste bloque bien.
- `ignore_order` à 1 débloque le film même si son précédent est en liste.
- Marquer le précédent « déjà vu » par le rattrapage débloque la suite, sans autre action.
- Trois films d'une saga, les deux premiers non vus : c'est le premier qui est nommé.
- Migration 7 rejouée deux fois sans effet, sans perte sur une copie de la production.
