<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Maintenance;

use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScopeResolverInterface;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Maintenance\TaskInterface;

/**
 * Change Control announces no approval or rejection, so the previews made while reviewing a
 * change set are removed here once it is closed.
 *
 * @internal
 */
final readonly class ScopedPreviewCleanupTask implements TaskInterface
{
    public function __construct(
        private PreviewService $previews,
        private PreviewScopeResolverInterface $scopes,
    ) {
    }

    public function execute(): void
    {
        $this->previews->removeClosedScopes($this->scopes->isOpen(...));
    }
}
