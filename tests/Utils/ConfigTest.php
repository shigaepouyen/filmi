<?php
namespace App\Tests\Utils;

use App\Utils\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testLoadFallsBackToExampleDefaults(): void
    {
        $config = Config::load(__DIR__ . '/fixtures/config_absent.php');

        $this->assertSame('', $config['tmdb_api_key']);
        $this->assertSame('fr-FR', $config['tmdb_language']);
        $this->assertSame('FR', $config['tmdb_region']);
        $this->assertSame('19:15', $config['default_start_time']);
    }

    public function testUserValuesOverrideDefaults(): void
    {
        $path = sys_get_temp_dir() . '/filmi_config_test.php';
        file_put_contents($path, "<?php return ['tmdb_api_key' => 'abc123'];");

        $config = Config::load($path);

        $this->assertSame('abc123', $config['tmdb_api_key']);
        $this->assertSame('fr-FR', $config['tmdb_language']);

        unlink($path);
    }
}
