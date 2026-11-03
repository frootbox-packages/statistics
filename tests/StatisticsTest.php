<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Tests;

use DateTimeImmutable;
use Frootbox\Core\Cache\PageCache;
use Frootbox\Core\Config\Environment;
use Frootbox\Core\Design\DesignPage;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Help\HelpLibrary;
use Frootbox\Core\Http\Exception\HttpException;
use Frootbox\Core\Page\CreatePage;
use Frootbox\Core\Page\PageHandler;
use Frootbox\Core\Page\PageOutline;
use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\ProjectPaths;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Core\Tests\Support\FixtureSite;
use Frootbox\Core\Tests\Support\NotesTestCase;
use Frootbox\Core\Url\Urls;
use Frootbox\Core\User\Roles;
use Frootbox\Statistics\Counting\CountingScript;
use Frootbox\Statistics\Counting\Hit;
use Frootbox\Statistics\Counting\HitHandler;
use Frootbox\Statistics\Counting\HitLog;
use Frootbox\Statistics\Report\DeviceCount;
use Frootbox\Statistics\Report\PageCount;
use Frootbox\Statistics\Report\Report;
use Frootbox\Statistics\Report\ShowReport;
use Frootbox\Statistics\Report\SourceCount;
use Frootbox\Statistics\Report\Tally;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Hit::class)]
#[CoversClass(HitHandler::class)]
#[CoversClass(HitLog::class)]
#[CoversClass(CountingScript::class)]
#[CoversClass(Tally::class)]
#[CoversClass(ShowReport::class)]
#[CoversClass(Report::class)]
final class StatisticsTest extends NotesTestCase
{
    private const string BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 15_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/19.0 Safari/605.1.15';

    private string $start;

    private string $coffee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->start = $this->page(null, 'Start');
        $this->coffee = $this->page($this->start, 'Kaffee');
        $this->runner()->run(new PublishPages([$this->start, $this->coffee]));
    }

    public function testCountsViewsAndVisitsOfPagesWithAnAddress(): void
    {
        $this->send(['target' => 'page:' . $this->start, 'language' => 'de', 'visit' => true, 'source' => 'www.Google.com', 'device' => 'mobile']);
        $this->send(['target' => 'page:' . $this->coffee, 'language' => 'de', 'visit' => false]);
        $this->send(['target' => 'page:' . $this->coffee, 'language' => 'de', 'visit' => true]);
        $this->send(['target' => 'page:01JZZZZZZZZZZZZZZZZZZZZZZZ', 'language' => 'de', 'visit' => true]);
        $this->send(['target' => 'page:' . $this->coffee, 'language' => 'fr']);

        $report = $this->report(7);

        self::assertSame([2, 3], [$report->visits, $report->views], 'Only targets with an address and languages of the website count.');
        self::assertSame([['Kaffee', '/kaffee', 2, 1], ['Start', '/', 1, 1]], array_map(static fn(PageCount $page): array => [$page->title, $page->url, $page->views, $page->visits], $report->pages));
        self::assertSame([['', 1], ['google.com', 1]], array_map(static fn(SourceCount $source): array => [$source->source, $source->visits], $report->sources), 'Without "www.", in lower case.');
        self::assertSame([['mobile', 1], ['tablet', 0], ['desktop', 1]], array_map(static fn(DeviceCount $device): array => [$device->device, $device->visits], $report->devices));
        self::assertCount(7, $report->perDay, 'Every day of the period, also without visits.');
        self::assertSame([date('Y-m-d'), 2, 3], [$report->perDay[6]->day, $report->perDay[6]->visits, $report->perDay[6]->views]);
        self::assertSame(0, $report->perDay[0]->views);
    }

    public function testLeavesOutBotsOtherWebsitesAndBrokenHits(): void
    {
        $hit = ['target' => 'page:' . $this->start, 'language' => 'de', 'visit' => true];

        self::assertSame(204, $this->send($hit, agent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
        $this->send($hit, agent: '');
        $this->send($hit, origin: 'https://example.com');
        $this->send([...$hit, 'source' => str_repeat('a', 1000)]);
        $this->send(null, raw: '{"target": "page:');
        $this->send(null, raw: 'null');
        $this->send(['target' => 'nothing', 'language' => 'de']);

        self::assertSame(0, $this->report(7)->views);

        $this->send($hit, origin: 'https://example.org');
        $this->send([...$hit, 'source' => '<script>', 'device' => 'fridge']);
        $report = $this->report(7);

        self::assertSame(2, $report->views, 'The own website counts.');
        self::assertSame([['', 2]], array_map(static fn(SourceCount $source): array => [$source->source, $source->visits], $report->sources), 'A source that is no host counts as direct.');
        self::assertSame(2, $report->devices[2]->visits, 'An unknown device counts as a computer.');
    }

    public function testAddsUpOnEveryRunOfTheScheduler(): void
    {
        $log = $this->get(HitLog::class);
        $today = date('Y-m-d');
        $log->append(new Hit($today, 'page:' . $this->start, 'de', true, 'newsletter', 'tablet'));
        $log->append(new Hit($today, 'page:' . $this->start, 'de'));

        $this->get(Tally::class)(new ScheduleRan(new DateTimeImmutable()));
        self::assertSame([], glob($this->get(ProjectPaths::class)->var(HitLog::DIRECTORY) . '/*.jsonl'), 'Counted files are gone.');

        $log->append(new Hit($today, 'page:' . $this->start, 'de', true, 'newsletter', 'tablet'));
        $this->get(Tally::class)->run();

        $rows = $this->connection()->fetchAllAssociative('SELECT target, language, views, visits FROM ' . Tally::PAGES);
        self::assertSame([['page:' . $this->start, 'de', 3, 2]], array_map(static fn(array $row): array => [$row['target'], $row['language'], self::number($row['views']), self::number($row['visits'])], $rows), 'One row a day, added to.');
        self::assertSame(2, self::number($this->connection()->fetchOne('SELECT visits FROM ' . Tally::SOURCES . ' WHERE source = ?', ['newsletter'])));
        self::assertSame(2, self::number($this->connection()->fetchOne('SELECT visits FROM ' . Tally::DEVICES . ' WHERE device = ?', ['tablet'])));
    }

    public function testKeepsTheMostFrequentSourcesOfAFile(): void
    {
        $log = $this->get(HitLog::class);
        $today = date('Y-m-d');

        for ($i = 1; $i <= 52; ++$i) {
            for ($n = 0; $n < ($i <= 50 ? 2 : 1); ++$n) {
                $log->append(new Hit($today, 'page:' . $this->start, 'de', true, 'site' . $i . '.example', 'desktop'));
            }
        }

        $sources = $this->report(30)->sources;

        self::assertCount(10, $sources, 'The report shows the ten most frequent.');
        self::assertSame(51, self::number($this->connection()->fetchOne('SELECT COUNT(*) FROM ' . Tally::SOURCES)), 'Kept: 50 and OTHER.');
        self::assertSame(2, self::number($this->connection()->fetchOne('SELECT visits FROM ' . Tally::SOURCES . ' WHERE source = ?', [Tally::OTHER])));
    }

    public function testComparesWithTheDaysBefore(): void
    {
        $log = $this->get(HitLog::class);
        $target = 'page:' . $this->coffee;

        foreach (['-1 day' => 3, '-6 days' => 1, '-7 days' => 2, '-13 days' => 2, '-14 days' => 5] as $when => $visits) {
            for ($n = 0; $n < $visits; ++$n) {
                $log->append(new Hit(new DateTimeImmutable($when)->format('Y-m-d'), $target, 'de', true));
            }
        }

        $report = $this->report(7);

        self::assertSame([new DateTimeImmutable('-6 days')->format('Y-m-d'), date('Y-m-d')], [$report->from, $report->to]);
        self::assertSame([4, 4], [$report->visits, $report->visitsBefore], 'The seven days before the seven days.');
        self::assertSame(0, $report->visitsChange());
        self::assertNull($this->report(30)->visitsChange(), 'Nothing before, nothing to compare.');
        self::assertSame(3, $report->perDay[5]->visits);
    }

    public function testAddsTheScriptToPagesForVisitors(): void
    {
        $html = $this->html('/kaffee');

        self::assertStringContainsString('data-target="page:' . $this->coffee . '" data-language="de" data-url="/_statistics/hit" defer', $html);
        self::assertMatchesRegularExpression('#<script src="/assets/frootbox/statistics/assets/count\.js\?v=\d+"#', $html);
        $this->get(PageCache::class)->clear();
        self::assertStringNotContainsString('count.js', $this->html('/kaffee', [DesignPage::class => true]), 'Not on the design page.');

        try {
            $this->html('/tee');
            self::fail('No page there.');
        } catch (HttpException $error) {
            self::assertSame(404, $error->status);
        }
    }

    public function testGivesEditorsTheReportAndBringsItsHelp(): void
    {
        $editor = $this->get(Roles::class)->fetchOne(['where' => ['name' => 'editor']]);
        self::assertNotNull($editor);
        self::assertContains('frootbox/statistics/*', $editor->permissions);

        $help = $this->get(HelpLibrary::class)->get('frootbox/statistics/statistics');
        self::assertNotNull($help);
        self::assertSame(['Statistics', 'Statistik'], [$help->title('en'), $help->title('de')]);
    }

    protected function tables(): array
    {
        return [Tally::PAGES, Tally::SOURCES, Tally::DEVICES];
    }

    protected function environment(): Environment
    {
        return Environment::Production;
    }

    protected function extensions(): Extensions
    {
        $statistics = ExtensionPackage::fromManifest('frootbox/statistics', (string) realpath(__DIR__ . '/..'), psr4: ['Frootbox\Statistics\\' => ['src/'], 'Frootbox\Statistics\Migrations\\' => ['migrations/']]);

        return new Extensions([...FixtureSite::extensions('base', 'site')->packages, $statistics], 'statistics');
    }

    protected function config(): array
    {
        return ['app' => ['name' => 'Testsite', 'url' => 'https://example.org'], 'theme' => 'acme/site', 'languages' => ['de' => ['title' => 'Deutsch'], 'en' => ['title' => 'English']]];
    }

    private function page(?string $parentId, string $title): string
    {
        return $this->runner()->run(new CreatePage('acme/base/Page', ['de' => ['title' => $title, 'content' => ['fields' => ['heading' => $title]]]], $parentId))->id;
    }

    /**
     * Sends a hit like the counting script does.
     *
     * @param array<string, mixed>|null $hit
     */
    private function send(?array $hit, string $agent = self::BROWSER, string $origin = '', ?string $raw = null): int
    {
        $request = new ServerRequest('POST', 'https://example.org' . HitHandler::ROUTE, array_filter(['User-Agent' => $agent, 'Origin' => $origin], static fn(string $value): bool => '' !== $value), $raw ?? json_encode($hit, \JSON_THROW_ON_ERROR));

        return $this->get(HitHandler::class)->handle($request)->getStatusCode();
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : -1;
    }

    private function report(int $days): Report
    {
        // As in a new request, see PageHandler.
        $this->get(PageOutline::class)->clear();
        $this->get(Urls::class)->clear();

        return $this->runner()->ask(new ShowReport($days));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function html(string $path, array $attributes = []): string
    {
        $request = new ServerRequest('GET', 'https://example.org' . $path);

        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return (string) $this->get(PageHandler::class)->handle($request)->getBody();
    }
}
