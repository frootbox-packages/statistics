<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Report;

/**
 * The statistics of the last days up to today, with the days before for
 * comparison (decision 0229).
 */
final readonly class Report
{
    /**
     * @param string            $from    The first day, e.g. "2026-10-05"
     * @param string            $to      The last day, today
     * @param list<DayCount>    $perDay  Every day from the first to the last, also without visits
     * @param list<PageCount>   $pages   The most viewed first
     * @param list<SourceCount> $sources The most visits first
     * @param list<DeviceCount> $devices Mobile, tablet, desktop
     */
    public function __construct(
        public int $days,
        public string $from,
        public string $to,
        public int $visits,
        public int $views,
        public int $visitsBefore,
        public int $viewsBefore,
        public array $perDay,
        public array $pages,
        public array $sources,
        public array $devices,
    ) {}

    /**
     * The change of the visits against the days before in percent, e.g.
     * 12 or -5; null without visits before.
     */
    public function visitsChange(): ?int
    {
        return self::change($this->visits, $this->visitsBefore);
    }

    public function viewsChange(): ?int
    {
        return self::change($this->views, $this->viewsBefore);
    }

    private static function change(int $now, int $before): ?int
    {
        return 0 === $before ? null : (int) round(($now - $before) / $before * 100);
    }
}
