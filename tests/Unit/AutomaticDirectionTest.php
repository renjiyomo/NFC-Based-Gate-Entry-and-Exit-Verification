<?php

namespace Tests\Unit;

use App\Models\AccessEvent;
use App\Services\GateOperations\AutomaticDirection;
use App\Services\GateOperations\OperatingDay;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AutomaticDirectionTest extends TestCase
{
    private function event(string $direction, string $time): AccessEvent
    {
        $event = new AccessEvent;
        $event->setRawAttributes(['id' => 42, 'direction' => $direction, 'confirmed_at' => CarbonImmutable::parse($time, 'Asia/Manila')->utc()->format('Y-m-d H:i:s.u')]);

        return $event;
    }

    #[DataProvider('scenarios')]
    public function test_direction_and_suppression_respect_real_day_boundaries(?string $previous, ?string $time, string $now, string $cutoff, string $direction, bool $duplicate, ?int $missing): void
    {
        $latest = $previous ? $this->event($previous, $time) : null;
        $original = $latest?->getRawOriginal();
        $at = CarbonImmutable::parse($now, 'Asia/Manila');
        $result = (new AutomaticDirection(new OperatingDay))->decide($latest, $at, $cutoff, 10);
        $this->assertSame($direction, $result['direction']);
        $this->assertSame($duplicate, $result['duplicate']);
        $this->assertSame($missing, $result['missing_exit_event_id']);
        $this->assertSame($original, $latest?->getRawOriginal());
        $this->assertTrue($at->greaterThanOrEqualTo($result['starts_at']) && $at->lessThan($result['ends_at']));
    }

    public static function scenarios(): array
    {
        return [
            'first scan' => [null, null, '2026-11-10 08:00:00', '00:00', 'entry', false, null],
            'second scan' => ['entry', '2026-11-10 08:00:00', '2026-11-10 09:00:00', '00:00', 'exit', false, null],
            'third scan' => ['exit', '2026-11-10 09:00:00', '2026-11-10 10:00:00', '00:00', 'entry', false, null],
            'yesterday missing exit' => ['entry', '2026-11-09 08:00:00', '2026-11-10 08:00:00', '00:00', 'entry', false, 42],
            'yesterday closed' => ['exit', '2026-11-09 18:00:00', '2026-11-10 08:00:00', '00:00', 'entry', false, null],
            'held entry coin' => ['entry', '2026-11-10 08:00:00', '2026-11-10 08:00:09.999999', '00:00', 'entry', true, null],
            'held exit coin' => ['exit', '2026-11-10 09:00:00', '2026-11-10 09:00:01', '00:00', 'exit', true, null],
            'same instant' => ['entry', '2026-11-10 08:00:00', '2026-11-10 08:00:00', '00:00', 'entry', true, null],
            'ten seconds elapsed' => ['entry', '2026-11-10 08:00:00', '2026-11-10 08:00:10', '00:00', 'exit', false, null],
            'midnight duplicate' => ['entry', '2026-11-09 23:59:58', '2026-11-10 00:00:02', '00:00', 'entry', true, null],
            'midnight reset' => ['entry', '2026-11-09 23:00:00', '2026-11-10 00:00:00', '00:00', 'entry', false, 42],
            'legitimate overnight exit' => ['entry', '2026-11-09 22:00:00', '2026-11-10 02:00:00', '04:00', 'exit', false, null],
            'overnight cutoff reset' => ['entry', '2026-11-09 22:00:00', '2026-11-10 04:00:00', '04:00', 'entry', false, 42],
            'duplicate spans custom cutoff' => ['entry', '2026-11-10 03:59:58', '2026-11-10 04:00:02', '04:00', 'entry', true, null],
            'long absence' => ['entry', '2026-10-01 08:00:00', '2026-11-10 08:00:00', '00:00', 'entry', false, 42],
        ];
    }

    public function test_operating_day_uses_manila_label_and_utc_half_open_bounds_without_mutating_input(): void
    {
        $at = Carbon::parse('2026-11-09 18:00:00', 'UTC');
        $original = $at->toIso8601String();
        $day = (new OperatingDay)->containing($at, '04:00');
        $this->assertSame('2026-11-09', $day['label']);
        $this->assertSame('2026-11-08T20:00:00+00:00', $day['starts_at']->toIso8601String());
        $this->assertSame('2026-11-09T20:00:00+00:00', $day['ends_at']->toIso8601String());
        $this->assertSame($original, $at->toIso8601String());
    }

    #[DataProvider('invalidCutoffs')]
    public function test_invalid_cutoffs_fail_closed(string $cutoff): void
    {
        $this->expectException(\LogicException::class);
        (new OperatingDay)->containing(CarbonImmutable::now(), $cutoff);
    }

    public static function invalidCutoffs(): array
    {
        return array_map(fn ($v) => [$v], ['', '24:00', '04:60', '4:00', '00:00:00', ' 00:00', "00:00\n"]);
    }

    #[DataProvider('invalidWindows')]
    public function test_invalid_duplicate_windows_fail_closed(int $seconds): void
    {
        $this->expectException(\LogicException::class);
        (new AutomaticDirection(new OperatingDay))->decide(null, CarbonImmutable::now(), '00:00', $seconds);
    }

    public static function invalidWindows(): array
    {
        return [[0], [-1], [121]];
    }

    public function test_future_passage_does_not_silently_toggle_state(): void
    {
        $this->expectException(\LogicException::class);
        (new AutomaticDirection(new OperatingDay))->decide($this->event('entry', '2026-11-10 10:00:00'), CarbonImmutable::parse('2026-11-10 09:00:00', 'Asia/Manila'), '00:00', 10);
    }
}
