<?php
namespace App\Tests\Repositories;

use App\Repositories\SettingRepository;
use App\Tests\Support\DbTestCase;

class SettingRepositoryTest extends DbTestCase
{
    private SettingRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SettingRepository($this->db);
    }

    public function testGetReturnsTheDefaultWhenAbsent(): void
    {
        $this->assertNull($this->repo->get('absent'));
        $this->assertSame('secours', $this->repo->get('absent', 'secours'));
    }

    public function testSetThenGet(): void
    {
        $this->repo->set('default_start_time', '20:00');

        $this->assertSame('20:00', $this->repo->get('default_start_time'));
    }

    public function testSetOverwritesWithoutDuplicating(): void
    {
        $this->repo->set('default_start_time', '20:00');
        $this->repo->set('default_start_time', '19:30');

        $this->assertSame('19:30', $this->repo->get('default_start_time'));
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM settings')->fetchColumn());
    }

    public function testStartTimeFallsBackOnTheDefault(): void
    {
        $this->assertSame('19:15', $this->repo->startTime());

        $this->repo->set('default_start_time', '20:45');
        $this->assertSame('20:45', $this->repo->startTime());
    }

    public function testStartTimeRejectsAMalformedStoredValue(): void
    {
        $this->repo->set('default_start_time', 'n importe quoi');

        $this->assertSame('19:15', $this->repo->startTime());
    }
}
