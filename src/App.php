<?php
declare(strict_types=1);

namespace App;

use App\Repositories\MovieRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\SeanceRepository;
use App\Repositories\SettingRepository;
use App\Repositories\VoteRepository;
use App\Services\TmdbService;
use App\Utils\Config;
use App\Utils\Database;
use App\Utils\Security;
use App\Utils\Session;

final class App
{
    public readonly ProfileRepository $profiles;
    public readonly MovieRepository $movies;
    public readonly VoteRepository $votes;
    public readonly SeanceRepository $seances;
    public readonly SettingRepository $settings;
    public readonly TmdbService $tmdb;

    private static ?self $instance = null;

    private function __construct()
    {
        Session::start();
        $db = Database::connect();

        $this->profiles = new ProfileRepository($db);
        $this->movies = new MovieRepository($db);
        $this->votes = new VoteRepository($db);
        $this->seances = new SeanceRepository($db, $this->movies);
        $this->settings = new SettingRepository($db);
        $this->tmdb = new TmdbService(
            (string) Config::get('tmdb_api_key'),
            (string) Config::get('tmdb_language'),
            (string) Config::get('tmdb_region')
        );
    }

    public static function boot(): self
    {
        return self::$instance ??= new self();
    }

    public function requireProfile(): array
    {
        return Session::requireProfile($this->profiles);
    }

    /**
     * Meme garde que requireProfile(), mais pour un endpoint JSON : sans profil,
     * une redirection renverrait du HTML a du code qui attend du JSON, et le
     * navigateur echouerait sur un message illisible plutot que sur un 401 clair.
     *
     * @return array<string, mixed>
     */
    public function requireProfileJson(): array
    {
        Session::start();
        $id = Session::currentProfileId();
        $profile = $id !== null ? $this->profiles->find($id) : null;

        if ($profile === null) {
            $this->json(['error' => 'Choisis un profil pour continuer.'], 401);
        }

        return $profile;
    }

    public function requirePost(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if ($method !== 'POST' || !Security::checkCsrf(is_string($token) ? $token : null)) {
            $this->json(['error' => 'Requête refusée.'], 400);
        }
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], string $title = 'Filmi'): void
    {
        $app = $this;
        $profile = Session::currentProfileId() !== null
            ? $this->profiles->find((int) Session::currentProfileId())
            : null;

        extract($data, EXTR_SKIP);
        $viewPath = dirname(__DIR__) . '/views/' . $view . '.php';

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /** @param array<string, mixed> $payload */
    public function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
