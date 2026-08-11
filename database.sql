CREATE TABLE IF NOT EXISTS profiles (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    name     TEXT NOT NULL,
    slug     TEXT UNIQUE NOT NULL,
    side     TEXT NOT NULL CHECK (side IN ('adult','kid')),
    avatar   TEXT NOT NULL,           -- clé du catalogue d'avatars
    color    TEXT NOT NULL DEFAULT 'indigo'
);

CREATE TABLE IF NOT EXISTS movies (
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
    providers      TEXT,              -- JSON plateformes FR (voir App\Utils\Providers)
    providers_at   DATETIME,          -- date du cache plateformes
    trailer_url    TEXT,              -- URL YouTube complète de la bande-annonce, ou NULL
    pool           TEXT NOT NULL CHECK (pool IN ('adult','kid')),
    bet_type       TEXT CHECK (bet_type IN ('safe','discovery')),
    memo           TEXT,
    added_by       INTEGER NOT NULL REFERENCES profiles(id),
    status         TEXT NOT NULL DEFAULT 'pool'
                   CHECK (status IN ('pool','watched','archived')),
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_movies_pool ON movies(pool, status);

CREATE TABLE IF NOT EXISTS votes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    movie_id   INTEGER NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (movie_id, profile_id)
);

CREATE TABLE IF NOT EXISTS seances (
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

CREATE TABLE IF NOT EXISTS seance_picks (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    seance_id     INTEGER NOT NULL REFERENCES seances(id) ON DELETE CASCADE,
    movie_id      INTEGER NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
    role          TEXT NOT NULL CHECK (role IN ('shortlist','chosen','vetoed')),
    by_profile_id INTEGER REFERENCES profiles(id),
    reason        TEXT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_picks_movie ON seance_picks(movie_id, role);

CREATE TABLE IF NOT EXISTS ratings (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    seance_id  INTEGER NOT NULL REFERENCES seances(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    score      INTEGER NOT NULL CHECK (score BETWEEN 1 AND 5),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (seance_id, profile_id)
);

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);
-- default_start_time = '19:15'
