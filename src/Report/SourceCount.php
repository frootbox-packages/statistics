<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Report;

/**
 * Where visits came from: a host such as "google.com", a campaign such as
 * "newsletter", "" for none (typed or bookmarked) and "*" for the rest.
 */
final readonly class SourceCount
{
    public function __construct(
        public string $source,
        public int $visits,
    ) {}
}
