<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Statistics\Counting;

use Frootbox\Core\Asset\PublicAssets;
use Frootbox\Core\Auth\Authentication;
use Frootbox\Core\Design\DesignPage;
use Frootbox\Core\Page\EditMode;
use Frootbox\Core\Preview\PreviewAccess;
use Frootbox\Core\View\BuildingHead;
use Frootbox\RichText\Html;

/**
 * Adds the counting script to every page of the website that visitors
 * see (decision 0229), also to the pages in the cache. Signed-in users,
 * the editing mode, previews, the design page and error pages count not.
 */
final readonly class CountingScript
{
    public const string SCRIPT = __DIR__ . '/../../assets/count.js';

    public function __construct(
        private PublicAssets $public,
        private EditMode $editMode,
    ) {}

    public function __invoke(BuildingHead $event): void
    {
        $request = $event->request;

        if (null === $event->target
            || $this->editMode->active()
            || null !== $request->getAttribute(Authentication::class)
            || null !== $request->getAttribute(PreviewAccess::class)
            || true === $request->getAttribute(DesignPage::class)) {
            return;
        }

        $event->head->add(\sprintf(
            '<script src="%s" data-target="%s" data-language="%s" data-url="%s" defer></script>',
            Html::escape($this->public->versioned(self::SCRIPT)),
            Html::escape((string) $event->target),
            Html::escape($event->head->language),
            HitHandler::ROUTE,
        ));
    }
}
