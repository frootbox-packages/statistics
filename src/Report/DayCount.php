<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Report;

/**
 * Visits and page views of one day, e.g. "2026-11-03".
 */
final readonly class DayCount
{
    public function __construct(
        public string $day,
        public int $visits,
        public int $views,
    ) {}
}
