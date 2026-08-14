# Filmi v4, le rattrapage d'historique

**Contexte critique** : l'application est **en production** avec les données réelles de la famille, 21 films et une séance en cours. Toute modification de schéma passe par une migration versionnée. Sauvegardes dans `~/Documents/Scripts/shigaepouyen/filmi-backups/`.

## Le besoin

Reconstituer le passé : ajouter un film ou une série et déclarer « déjà vu le tel jour », pour que l'historique et le palmarès ne démarrent pas à l'installation de l'application. La séance créée doit tenir compte de la liste d'où vient l'œuvre, celle des parents ou celle des filles.

## Décisions prises

| Sujet | Décision |
|---|---|
| Série déjà vue en entier | **Une seule entrée** à la date de fin. La série passe intégralement en vu, l'historique affiche « 24 épisodes ». Pas de saisie épisode par épisode. |
| Palmarès | Les œuvres rattrapées **comptent comme les autres** : œuvres vues, note moyenne, meilleur film. Pas de pastille distinctive dans l'historique. |
| Alternance | Une séance de rattrapage est **exclue du calcul de l'alternance**. Décision technique, pas une préférence : voir ci-dessous. |
| Qui peut rattraper | La règle d'accès habituelle. Rattraper, c'est gérer une liste : les parents sur les deux, les filles sur la leur. |

## Pourquoi exclure le rattrapage de l'alternance

`ScheduleService::defaultChooserSide()` parcourt les séances par date décroissante et prend la première terminée pour en déduire le camp opposé. Saisir « vu le 10 août, choisi par les filles » rendrait cette séance la plus récente et **basculerait silencieusement le tour du samedi à venir**.

L'historique ne doit pas réécrire le présent. `recentForSchedule()` filtre donc sur `backfilled = 0`.

## Migration v5

`seances.backfilled INTEGER NOT NULL DEFAULT 0`.

Une seule colonne. Les séances existantes valent 0, ce sont de vrais samedis.

## Comportement

Créer un rattrapage, c'est écrire une séance passée :

- `date` = la date saisie, `chooser_side` = **le pool de l'œuvre**, `status = 'done'`, `backfilled = 1`, `movie_id` renseigné.
- **Aucune ligne de shortlist**, donc aucun créneau de cooldown consommé, exactement comme une semaine des filles.
- L'œuvre passe en `status = 'watched'` et quitte les listes et le tirage.
- Pour une série : `episodes_watched = episode_count`, `episodes_from = 1`, `episodes_to = episode_count`, et `episodes_label` du type « 24 épisodes ».
- La séance est notable comme une autre, via le lien de l'historique vers `seance.php?id=`.

### Les cas d'erreur, à traiter proprement

| Cas | Comportement attendu |
|---|---|
| Une séance existe déjà à cette date | Refus avec un message nommant ce qui s'y trouve déjà. `seances.date` est en `UNIQUE`, contrainte posée pour empêcher deux téléphones de créer deux séances le même samedi : elle reste. Jamais de 500. |
| Date dans le futur | Refus : « déjà vu » ne peut pas être demain. |
| Date invalide ou absente | Refus avec message. |
| L'œuvre est déjà vue | Refus, elle a déjà sa séance. |
| Liste interdite au profil | Refus côté serveur via `Access::canManagePool()`. |

## Ce qui est construit

### Logique

| Unité | Rôle |
|---|---|
| `Migrations` migration 5 | La colonne `backfilled` |
| `SeanceRepository::recordBackfill()` | Transactionnelle : crée la séance passée, passe l'œuvre en vu, remplit la progression si c'est une série. Lève une exception explicite sur date déjà prise, date future, œuvre déjà vue. |
| `SeanceRepository::recentForSchedule()` | Filtre `backfilled = 0` |
| `SeanceRepository::history()` | Expose `backfilled`, pour que la vue puisse en tenir compte plus tard si besoin |

### Écrans

| Écran | Modification |
|---|---|
| `views/add.php` et `public/add.php` | Une case « déjà vu le », avec un champ date. Cochée, l'œuvre est ajoutée puis immédiatement rattrapée. Le tag de pari reste demandé pour la liste des parents, il documente le choix même a posteriori. |
| `views/movie.php` et `public/movie.php` | Sur une œuvre encore en liste, une action « déjà vu le » avec son champ date. Même règle d'accès que l'archivage. |
| `views/history.php` | Rien de spécial à afficher, les rattrapages se fondent dans la timeline. Vérifier seulement qu'une date en semaine s'affiche correctement. |

## Vérifications attendues

- Migration 5 rejouée deux fois sans effet, et sans perte sur une copie de la base de production.
- Un rattrapage de film crée bien une séance à la bonne date avec le camp du pool, et l'œuvre quitte le tirage.
- Un rattrapage de série marque tous les épisodes vus et l'historique affiche le nombre d'épisodes.
- **Le tour du samedi à venir ne change pas** après un rattrapage plus récent que la dernière vraie séance. C'est la vérification la plus importante.
- Aucun créneau de cooldown consommé par un rattrapage.
- Les cinq cas d'erreur du tableau renvoient un message, jamais une erreur 500.
- Une fille ne peut pas rattraper une œuvre de la liste des parents, refus côté serveur.
