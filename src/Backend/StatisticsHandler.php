<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Statistics\Report\ShowReport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The screen "Statistics": visits and page views of the last 7, 30, 90
 * or 365 days, per day, the most viewed pages, sources and devices
 * (decision 0229).
 */
final readonly class StatisticsHandler implements RequestHandlerInterface
{
    public const string PERMISSION = 'frootbox/statistics/report.show';

    public const array PERIODS = [7, 30, 90, 365];

    public function __construct(
        private BackendView $view,
        private Runner $runner,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        $days = $request->getQueryParams()['days'] ?? null;
        $days = \is_string($days) && \in_array((int) $days, self::PERIODS, true) ? (int) $days : 30;

        return $this->view->render($request, '@frootbox/statistics/backend/statistics.twig', [
            'report' => $this->runner->ask(new ShowReport($days)),
            'periods' => self::PERIODS,
        ]);
    }
}
