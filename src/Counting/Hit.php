<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Counting;

use Frootbox\Core\Url\Target;

/**
 * One page view as the browser reports it (decision 0229): the target and
 * language of the page, whether it starts a visit, and only then where
 * the visitor came from and the kind of device. Nothing that tells
 * visitors apart.
 */
final readonly class Hit
{
    public const string DIRECT = '';

    public const array DEVICES = ['mobile', 'tablet', 'desktop'];

    private const string SOURCE = '/^[a-z0-9](?:[a-z0-9.-]{0,98}[a-z0-9])?$/';

    /**
     * @param string $day    The day in the time zone of the website, e.g. "2026-11-03"
     * @param string $source The host of the referring website without "www.", or DIRECT; only for a visit
     * @param string $device One of DEVICES; only for a visit
     */
    public function __construct(
        public string $day,
        public string $target,
        public string $language,
        public bool $visit = false,
        public string $source = self::DIRECT,
        public string $device = '',
    ) {}

    /**
     * The hit from what the browser sent, or null if it is not one.
     *
     * @param list<string> $languages The languages of the website
     */
    public static function fromBrowser(mixed $data, string $day, array $languages): ?self
    {
        if (!\is_array($data) || !\is_string($data['target'] ?? null) || !\is_string($data['language'] ?? null)) {
            return null;
        }

        $target = Target::tryParse($data['target']);

        if (null === $target || \strlen($data['target']) > 150 || !\in_array($data['language'], $languages, true)) {
            return null;
        }

        if (true !== ($data['visit'] ?? false)) {
            return new self($day, (string) $target, $data['language']);
        }

        $device = \is_string($data['device'] ?? null) && \in_array($data['device'], self::DEVICES, true) ? $data['device'] : 'desktop';

        return new self($day, (string) $target, $data['language'], true, self::source($data['source'] ?? null), $device);
    }

    /**
     * The hit from a line of the log, or null if the line is broken.
     */
    public static function fromLine(string $line): ?self
    {
        $data = json_decode($line, true);

        if (!\is_array($data) || !\is_string($data['day'] ?? null) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['day']) || !\is_string($data['target'] ?? null) || !\is_string($data['language'] ?? null)) {
            return null;
        }

        return new self(
            $data['day'],
            $data['target'],
            $data['language'],
            true === ($data['visit'] ?? false),
            \is_string($data['source'] ?? null) ? $data['source'] : self::DIRECT,
            \is_string($data['device'] ?? null) ? $data['device'] : '',
        );
    }

    public function toLine(): string
    {
        return json_encode(['day' => $this->day, 'target' => $this->target, 'language' => $this->language, 'visit' => $this->visit, 'source' => $this->source, 'device' => $this->device], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * A host in lower case without "www.", e.g. "google.com"; anything
     * else counts as a direct visit.
     */
    private static function source(mixed $source): string
    {
        if (!\is_string($source)) {
            return self::DIRECT;
        }

        $source = strtolower(trim($source));
        $source = str_starts_with($source, 'www.') ? substr($source, 4) : $source;

        return 1 === preg_match(self::SOURCE, $source) ? $source : self::DIRECT;
    }
}
