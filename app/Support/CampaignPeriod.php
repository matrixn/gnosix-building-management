<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignPeriod
{
    public const TIMEZONE = 'Europe/Bucharest';

    public function __construct(public readonly string $name, public readonly CarbonImmutable $start, public readonly CarbonImmutable $end) {}

    public static function from(array $input): self
    {
        $today = CarbonImmutable::today(self::TIMEZONE);
        $name = $input['period'] ?? 'all';
        if ($name === 'custom') {
            $start = CarbonImmutable::parse($input['from'], self::TIMEZONE);
            $end = CarbonImmutable::parse($input['to'], self::TIMEZONE);
            if ($end->greaterThan($start->addDays(365))) {
                throw ValidationException::withMessages(['to' => 'Alege un interval de cel mult 366 de zile.']);
            }

            return new self($name, $start, $end);
        }

        return new self($name, $today->subDays(match ($name) {
            'today' => 0,
            '7d' => 6,
            default => 29,
        }), $today);
    }

    public function limited(): bool
    {
        return $this->name !== 'all';
    }

    public function daily(): Builder
    {
        return DB::table('campaign_daily_totals')->whereBetween('traffic_day', [$this->start->toDateString(), $this->end->toDateString()]);
    }

    public function series(iterable $rows): array
    {
        $byDay = collect($rows)->keyBy('traffic_day');
        $series = [];
        for ($day = $this->start; $day->lte($this->end); $day = $day->addDay()) {
            $row = $byDay->get($day->toDateString());
            $series[] = [
                'day' => $day->format('d.m.Y'),
                'date' => $day->toDateString(),
                'tagged_visits' => (int) ($row->tagged_visits ?? 0),
                'tagged_conversions' => (int) ($row->tagged_conversions ?? 0),
                'untagged_visits' => (int) ($row->untagged_visits ?? 0),
                'untagged_conversions' => (int) ($row->untagged_conversions ?? 0),
            ];
        }

        return $series;
    }
}
