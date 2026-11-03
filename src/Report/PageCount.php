<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Report;

/**
 * A page or another target with an address, e.g. a news article, in one
 * language. The title and URL are null if it has none any more.
 */
final readonly class PageCount
{
    public function __construct(
        public string $target,
        public string $language,
        public ?string $title,
        public ?string $url,
        public int $views,
        public int $visits,
    ) {}
}
