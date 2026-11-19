<?php

namespace App\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class Window
{
    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    public function __construct(array $filters, string $timezone)
    {
        $today = CarbonImmutable::today($timezone);
        $start = isset($filters['from']) ? CarbonImmutable::parse($filters['from'], $timezone) : $today->subDays(29);
        $last = isset($filters['to']) ? CarbonImmutable::parse($filters['to'], $timezone) : $today;
        if ($last->lt($start) || $last->gt($today) || $last->gt($start->addDays(365))) {
            throw ValidationException::withMessages(['to' => __('ops.range_invalid')]);
        }
        $this->start = $start;
        $this->end = $last->addDay();
    }

    public function range(bool $previous = false): array
    {
        $days = (int) $this->start->diffInDays($this->end);
        $shift = $previous ? $days : 0;

        return [$this->start->subDays($shift)->utc(), $this->end->subDays($shift)->utc()];
    }

    public function filters(): array
    {
        return ['from' => $this->start->toDateString(), 'to' => $this->end->subDay()->toDateString()];
    }
}
