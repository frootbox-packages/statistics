<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics;

use Frootbox\Backend\Screen;
use Frootbox\Core\Extension\Extension;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Core\View\BuildingHead;
use Frootbox\Statistics\Backend\StatisticsHandler;
use Frootbox\Statistics\Counting\CountingScript;
use Frootbox\Statistics\Counting\HitHandler;
use Frootbox\Statistics\Report\ShowReport;
use Frootbox\Statistics\Report\Tally;

/**
 * Statistics without cookies (decision 0229): a small script on every
 * page reports the view, the hits are logged in var/ and added up per
 * day by the scheduler and before every report. The screen comes only
 * with the backend.
 */
final class StatisticsExtension implements Extension
{
    public function register(Registry $registry): void
    {
        $registry->query(ShowReport::class);
        $registry->listen(BuildingHead::class, CountingScript::class);
        $registry->listen(ScheduleRan::class, Tally::class);
        $registry->post(HitHandler::ROUTE, HitHandler::class, 'frootbox.statistics.hit');

        if (class_exists(Screen::class)) {
            $registry->mount('backend.path', '/cms')
                ->get('/statistics', StatisticsHandler::class, 'frootbox.statistics.statistics');
            $registry->add(Screen::class, new Screen('Statistics', 'frootbox.statistics.statistics', Screen::WEBSITE, 'chart', StatisticsHandler::PERMISSION, order: 37));
        }
    }
}
