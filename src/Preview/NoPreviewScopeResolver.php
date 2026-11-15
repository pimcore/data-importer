<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Preview;

/**
 * Without Change Control there are no change sets, so no scope names one.
 *
 * @internal
 */
final readonly class NoPreviewScopeResolver implements PreviewScopeResolverInterface
{
    public function proposal(string $configName, PreviewScope $scope): ?array
    {
        return null;
    }

    public function isOpen(PreviewScope $scope): bool
    {
        return false;
    }
}
