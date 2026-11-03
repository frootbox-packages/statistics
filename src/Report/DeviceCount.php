<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Report;

/**
 * Visits from one kind of device: "mobile", "tablet" or "desktop".
 */
final readonly class DeviceCount
{
    public function __construct(
        public string $device,
        public int $visits,
    ) {}
}
