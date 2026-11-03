<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Counting;

use Frootbox\Core\ProjectPaths;
use Generator;

/**
 * Collects the hits in files below var/statistics/, one line each, so a
 * visit costs no write to the database (decisions 0175, 0229). The
 * scheduler and every report add them to the tables. A file stops
 * growing at MOST bytes a day, so nobody can fill the disk; what is lost
 * with var/ is at most what was not counted yet.
 */
final readonly class HitLog
{
    public const string DIRECTORY = 'statistics';

    public const int MOST = 20_000_000;

    public function __construct(private ProjectPaths $paths) {}

    public function append(Hit $hit): bool
    {
        $directory = $this->paths->var(self::DIRECTORY);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            return false;
        }

        $file = $directory . '/' . $hit->day . '.jsonl';
        clearstatcache(true, $file);

        if (is_file($file) && filesize($file) > self::MOST) {
            return false;
        }

        return false !== @file_put_contents($file, $hit->toLine() . "\n", \FILE_APPEND | \LOCK_EX);
    }

    /**
     * Hands the hits of each file to $count and removes the file once
     * $count returned. New hits go to a new file meanwhile. Only one run
     * at a time; null if another one is busy.
     *
     * @param callable(iterable<Hit>): void $count
     *
     * @return int|null The number of files
     */
    public function collect(callable $count): ?int
    {
        $directory = $this->paths->var(self::DIRECTORY);

        if (!is_dir($directory)) {
            return 0;
        }

        $lock = @fopen($directory . '/.lock', 'c');

        if (false === $lock) {
            return null;
        }

        if (!flock($lock, \LOCK_EX | \LOCK_NB)) {
            fclose($lock);

            return null;
        }

        try {
            foreach (self::files($directory . '/*.jsonl') as $file) {
                @rename($file, $file . '.' . bin2hex(random_bytes(4)) . '.work');
            }

            $files = 0;

            // Left over from a run that broke off, or taken just now.
            foreach (self::files($directory . '/*.work') as $work) {
                $handle = @fopen($work, 'r');

                if (false === $handle) {
                    continue;
                }

                // Waits for a write that began before the file was taken.
                flock($handle, \LOCK_SH);
                $count(self::read($handle));
                fclose($handle);
                @unlink($work);
                ++$files;
            }

            return $files;
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return list<string>
     */
    private static function files(string $pattern): array
    {
        $files = glob($pattern);

        return false === $files ? [] : $files;
    }

    /**
     * @param resource $handle
     *
     * @return Generator<int, Hit>
     */
    private static function read($handle): Generator
    {
        while (false !== ($line = fgets($handle))) {
            $hit = Hit::fromLine(trim($line));

            if (null !== $hit) {
                yield $hit;
            }
        }
    }
}
