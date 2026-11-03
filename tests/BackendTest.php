<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Tests;

use Frootbox\Backend\Tests\Support\BackendTestCase;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Statistics\Backend\StatisticsHandler;
use Frootbox\Statistics\Report\Tally;
use Frootbox\Statistics\StatisticsExtension;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StatisticsHandler::class)]
#[CoversClass(StatisticsExtension::class)]
final class BackendTest extends BackendTestCase
{
    public function testEditorsSeeTheStatistics(): void
    {
        $this->createUser('editor@example.org', ['editor']);
        $session = $this->signIn('editor@example.org');

        $empty = $this->html($this->request('GET', '/cms/statistics', session: $session));
        self::assertStringContainsString('href="/cms/statistics" aria-current="page"', $empty, 'The sidebar has the screen.');
        self::assertStringContainsString('Noch keine Besuche', $empty);
        self::assertStringContainsString('href="/cms/statistics?days=30" aria-current="page">Letzte 30 Tage</a>', $empty);

        $today = date('Y-m-d');
        $this->connection()->insert(Tally::PAGES, ['day' => $today, 'target' => 'page:01JZZZZZZZZZZZZZZZZZZZZZZZ', 'language' => 'de', 'views' => 1234, 'visits' => 321]);
        $this->connection()->insert(Tally::SOURCES, ['day' => $today, 'source' => 'google.com', 'visits' => 300]);
        $this->connection()->insert(Tally::SOURCES, ['day' => $today, 'source' => '', 'visits' => 21]);
        $this->connection()->insert(Tally::DEVICES, ['day' => $today, 'device' => 'mobile', 'visits' => 321]);

        $html = $this->html($this->request('GET', '/cms/statistics?days=7', session: $session));
        self::assertStringContainsString('href="/cms/statistics?days=7" aria-current="page">Letzte 7 Tage</a>', $html);
        self::assertStringContainsString('<dt class="fb-figure__label">Besuche</dt><dd class="fb-figure__value">321</dd>', $html);
        self::assertStringContainsString('<dd class="fb-figure__value">1.234</dd>', $html, 'With the separators of the language.');
        self::assertStringContainsString('Besuche je Tag', $html);
        self::assertStringContainsString('Entfernt', $html, 'A page without an address any more.');
        self::assertStringContainsString('google.com', $html);
        self::assertStringContainsString('Direkt oder unbekannt', $html);
        self::assertStringContainsString('Smartphone', $html);
    }

    public function testOthersDoNotSeeIt(): void
    {
        $this->createUser('nobody@example.org', []);
        $session = $this->signIn('nobody@example.org');

        $start = $this->html($this->request('GET', '/cms', session: $session));
        self::assertStringNotContainsString('href="/cms/statistics"', $start);
        self::assertSame(403, $this->request('GET', '/cms/statistics', session: $session)->getStatusCode());
    }

    protected function tables(): array
    {
        return [Tally::PAGES, Tally::SOURCES, Tally::DEVICES];
    }

    protected function extensions(): Extensions
    {
        $statistics = ExtensionPackage::fromManifest('frootbox/statistics', (string) realpath(__DIR__ . '/..'), psr4: ['Frootbox\Statistics\\' => ['src/'], 'Frootbox\Statistics\Migrations\\' => ['migrations/']]);

        return new Extensions([self::backend(), $statistics], 'statistics-backend');
    }

    protected function config(): array
    {
        return ['languages' => ['de' => ['title' => 'Deutsch']]];
    }
}
