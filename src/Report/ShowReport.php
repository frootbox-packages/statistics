<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Report;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Page\PageOutline;
use Frootbox\Core\Plugin\Plugins;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;
use Frootbox\Statistics\Counting\Hit;

/**
 * @implements Query<Report>
 */
#[AsQuery('frootbox/statistics/report.show', 'Shows the visits and page views of the website for the last days up to today: per day, the most viewed pages, where visitors came from and their devices, with the days before for comparison')]
final readonly class ShowReport implements Query
{
    private const int MOST = 10;

    public function __construct(
        #[Description('How many days up to today, e.g. 7, 30 or 365')]
        #[Range(min: 1, max: 366)]
        public int $days = 30,
    ) {}

    public function handle(Tally $tally, Connection $connection, Urls $urls, PageOutline $outline, Plugins $subpages, Languages $languages): Report
    {
        $tally->run();

        $today = new DateTimeImmutable('today');
        $from = $today->modify('-' . ($this->days - 1) . ' days');
        $range = [$from->format('Y-m-d'), $today->format('Y-m-d')];
        $before = [$from->modify('-' . $this->days . ' days')->format('Y-m-d'), $from->modify('-1 day')->format('Y-m-d')];

        [$visits, $views] = self::totals($connection, $range);
        [$visitsBefore, $viewsBefore] = self::totals($connection, $before);

        $days = [];

        foreach ($connection->fetchAllAssociative('SELECT day, SUM(visits) AS visits, SUM(views) AS views FROM ' . Tally::PAGES . ' WHERE day >= ? AND day <= ? GROUP BY day', $range) as $row) {
            $days[substr(self::string($row['day']), 0, 10)] = ['visits' => self::int($row['visits']), 'views' => self::int($row['views'])];
        }

        $perDay = [];

        for ($day = $from; $day <= $today; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $perDay[] = new DayCount($key, $days[$key]['visits'] ?? 0, $days[$key]['views'] ?? 0);
        }

        $pages = [];
        $codes = $languages->codes();

        foreach ($connection->fetchAllAssociative('SELECT target, language, SUM(views) AS views, SUM(visits) AS visits FROM ' . Tally::PAGES . ' WHERE day >= ? AND day <= ? GROUP BY target, language ORDER BY SUM(views) DESC, target, language LIMIT ' . self::MOST, $range) as $row) {
            $target = Target::tryParse(self::string($row['target']));
            $language = self::string($row['language']);
            $title = match (true) {
                null === $target => null,
                $target->isPage() => $outline->title($target->id, [$language, ...$codes]),
                default => $subpages->get($target->type)?->title($target->id, $language),
            };
            $pages[] = new PageCount(self::string($row['target']), $language, $title, null === $target ? null : $urls->href($target, $language), self::int($row['views']), self::int($row['visits']));
        }

        $sources = [];

        foreach ($connection->fetchAllAssociative('SELECT source, SUM(visits) AS visits FROM ' . Tally::SOURCES . ' WHERE day >= ? AND day <= ? GROUP BY source ORDER BY SUM(visits) DESC, source LIMIT ' . self::MOST, $range) as $row) {
            $sources[] = new SourceCount(self::string($row['source']), self::int($row['visits']));
        }

        $counted = [];

        foreach ($connection->fetchAllAssociative('SELECT device, SUM(visits) AS visits FROM ' . Tally::DEVICES . ' WHERE day >= ? AND day <= ? GROUP BY device', $range) as $row) {
            $counted[self::string($row['device'])] = self::int($row['visits']);
        }

        $devices = array_map(static fn(string $device): DeviceCount => new DeviceCount($device, $counted[$device] ?? 0), Hit::DEVICES);

        return new Report($this->days, $range[0], $range[1], $visits, $views, $visitsBefore, $viewsBefore, $perDay, $pages, $sources, $devices);
    }

    /**
     * @param array{string, string} $range
     *
     * @return array{int, int} Visits and views
     */
    private static function totals(Connection $connection, array $range): array
    {
        $row = $connection->fetchAssociative('SELECT SUM(visits) AS visits, SUM(views) AS views FROM ' . Tally::PAGES . ' WHERE day >= ? AND day <= ?', $range);

        return [self::int($row['visits'] ?? 0), self::int($row['views'] ?? 0)];
    }

    private static function string(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
