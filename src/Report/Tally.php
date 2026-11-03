<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Report;

use Doctrine\DBAL\Connection;
use Frootbox\Core\Action\Journal;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Core\Url\Urls;
use Frootbox\Statistics\Counting\Hit;
use Frootbox\Statistics\Counting\HitLog;

/**
 * Adds the logged hits to the counts of each day (decision 0229), on
 * every run of the scheduler and before every report. Bookkeeping like
 * the search index, not an action: nothing to undo.
 *
 * Only targets with a current address count, so made-up hits add no
 * rows. Of the sources in a file the SOURCES most frequent stay, the
 * others count as OTHER.
 */
final readonly class Tally
{
    public const string PAGES = 'frootbox_statistics_pages';

    public const string SOURCES = 'frootbox_statistics_sources';

    public const string DEVICES = 'frootbox_statistics_devices';

    public const string OTHER = '*';

    private const int SOURCES_PER_FILE = 50;

    public function __construct(
        private HitLog $log,
        private Connection $connection,
        private Journal $journal,
        private Urls $urls,
    ) {}

    public function run(): void
    {
        $this->log->collect($this->count(...));
    }

    /**
     * On every run of the scheduler.
     */
    public function __invoke(ScheduleRan $event): void
    {
        $this->run();
    }

    /**
     * @param iterable<Hit> $hits
     */
    private function count(iterable $hits): void
    {
        // Per day: "target language" => [views, visits], sources and devices => visits.
        $pages = [];
        $sources = [];
        $devices = [];
        $known = [];

        foreach ($hits as $hit) {
            $key = $hit->target . ' ' . $hit->language;
            $known[$key] ??= null !== $this->urls->path($hit->target, $hit->language);

            if (!$known[$key]) {
                continue;
            }

            $count = $pages[$hit->day][$key] ?? [0, 0];
            $pages[$hit->day][$key] = [$count[0] + 1, $count[1] + ($hit->visit ? 1 : 0)];

            if ($hit->visit) {
                $sources[$hit->day]['s' . $hit->source] = ($sources[$hit->day]['s' . $hit->source] ?? 0) + 1;
                $devices[$hit->day][$hit->device] = ($devices[$hit->day][$hit->device] ?? 0) + 1;
            }
        }

        if ([] === $pages) {
            return;
        }

        $this->journal->trusted(function () use ($pages, $sources, $devices): void {
            $this->connection->transactional(function () use ($pages, $sources, $devices): void {
                foreach ($pages as $day => $counts) {
                    foreach ($counts as $key => [$views, $visits]) {
                        [$target, $language] = explode(' ', $key, 2);
                        $this->add(self::PAGES, ['day' => $day, 'target' => $target, 'language' => $language], ['views' => $views, 'visits' => $visits]);
                    }
                }

                foreach ($sources as $day => $counts) {
                    foreach (self::fewer($counts) as [$source, $visits]) {
                        $this->add(self::SOURCES, ['day' => $day, 'source' => $source], ['visits' => $visits]);
                    }
                }

                foreach ($devices as $day => $counts) {
                    foreach ($counts as $device => $visits) {
                        $this->add(self::DEVICES, ['day' => $day, 'device' => $device], ['visits' => $visits]);
                    }
                }
            });
        });
    }

    /**
     * The most frequent sources, the rest as OTHER.
     *
     * @param array<string, int> $counts Keys with a leading "s", so PHP keeps them strings
     *
     * @return list<array{string, int}> Source and visits
     */
    private static function fewer(array $counts): array
    {
        arsort($counts);
        $kept = \array_slice($counts, 0, self::SOURCES_PER_FILE, true);
        $rest = array_sum(\array_slice($counts, self::SOURCES_PER_FILE));
        $fewer = [];

        foreach ($kept as $key => $visits) {
            $fewer[] = [substr($key, 1), $visits];
        }

        return $rest > 0 ? [...$fewer, [self::OTHER, $rest]] : $fewer;
    }

    /**
     * Adds to the counts of a row, or inserts it: portable, without an
     * upsert of one database.
     *
     * @param array<string, string> $key
     * @param array<string, int>    $counts
     */
    private function add(string $table, array $key, array $counts): void
    {
        $set = implode(', ', array_map(static fn(string $column): string => $column . ' = ' . $column . ' + ?', array_keys($counts)));
        $where = implode(' AND ', array_map(static fn(string $column): string => $column . ' = ?', array_keys($key)));
        $changed = $this->connection->executeStatement('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where, [...array_values($counts), ...array_values($key)]);

        if (0 === (int) $changed) {
            $this->connection->insert($table, [...$key, ...$counts]);
        }
    }
}
