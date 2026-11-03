<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Counting;

use Frootbox\Core\Language\Languages;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Takes the hit the counting script sends (decision 0229) and logs it.
 * Always answers 204, so a script learns nothing from the answer. Hits
 * from other websites, from bots and of more than SIZE bytes are left out.
 */
final readonly class HitHandler implements RequestHandlerInterface
{
    public const string ROUTE = '/_statistics/hit';

    private const int SIZE = 1000;

    private const string BOTS = '/bot|crawl|spider|slurp|fetch|preview|headless|lighthouse|pingdom|monitor|curl|wget|python|java\//i';

    public function __construct(
        private HitLog $log,
        private Languages $languages,
        private ResponseFactoryInterface $responses,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $hit = $this->accepts($request) ? Hit::fromBrowser(json_decode(substr((string) $request->getBody(), 0, self::SIZE + 1), true), date('Y-m-d'), $this->languages->codes()) : null;

        if (null !== $hit) {
            $this->log->append($hit);
        }

        return $this->responses->createResponse(204)->withHeader('Cache-Control', 'no-store');
    }

    private function accepts(ServerRequestInterface $request): bool
    {
        $agent = $request->getHeaderLine('User-Agent');

        if ('' === $agent || 1 === preg_match(self::BOTS, $agent) || $request->getBody()->getSize() > self::SIZE) {
            return false;
        }

        // Browsers send the origin with every POST; another website must not count here.
        $origin = $request->getHeaderLine('Origin');

        return '' === $origin || parse_url($origin, \PHP_URL_HOST) === $request->getUri()->getHost();
    }
}
