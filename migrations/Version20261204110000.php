<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Frootbox\Database\Migration;
use Frootbox\Database\Schema\Blueprint;

/**
 * The counts of each day (decision 0229): views and visits per page and
 * language, visits per source and per device. Nothing about a single
 * visitor. The role "editor" gets frootbox/statistics/*.
 */
final class Version20261204110000 extends Migration
{
    private const string RULE = 'frootbox/statistics/*';

    public function getDescription(): string
    {
        return 'Adds the tables of the statistics';
    }

    public function up(Schema $schema): void
    {
        $this->createTable('frootbox_statistics_pages', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('target', 150);
            $table->string('language', 35);
            $table->integer('views');
            $table->integer('visits');
            $table->unique(['day', 'target', 'language']);
        });

        $this->createTable('frootbox_statistics_sources', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('source', 100);
            $table->integer('visits');
            $table->unique(['day', 'source']);
        });

        $this->createTable('frootbox_statistics_devices', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('device', 10);
            $table->integer('visits');
            $table->unique(['day', 'device']);
        });

        $this->change(static fn(array $rules): array => \in_array(self::RULE, $rules, true) || \in_array('*', $rules, true) ? $rules : [...$rules, self::RULE]);
    }

    public function down(Schema $schema): void
    {
        $this->change(static fn(array $rules): array => array_values(array_filter($rules, static fn(mixed $rule): bool => self::RULE !== $rule)));
        $this->dropTable('frootbox_statistics_devices');
        $this->dropTable('frootbox_statistics_sources');
        $this->dropTable('frootbox_statistics_pages');
    }

    /**
     * Changes the permissions of the role "editor", if it is there.
     *
     * @param callable(list<mixed>): list<mixed> $change
     */
    private function change(callable $change): void
    {
        $rules = $this->connection->fetchOne('SELECT permissions FROM roles WHERE name = ?', ['editor']);

        if (!\is_string($rules)) {
            return;
        }

        $rules = json_decode($rules, true, flags: \JSON_THROW_ON_ERROR);

        if (\is_array($rules) && array_is_list($rules)) {
            $this->addSql('UPDATE roles SET permissions = ? WHERE name = ?', [json_encode($change($rules), \JSON_THROW_ON_ERROR), 'editor']);
        }
    }
}
