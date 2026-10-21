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
 * Binds a scoped preview to the change set it names.
 *
 * @internal
 */
interface PreviewScopeResolverInterface
{
    /**
     * What the change set proposes for the configuration — only when it is one the current
     * user may read and it holds that configuration; null otherwise.
     *
     * @return array<string, mixed>|null
     */
    public function proposal(string $configName, PreviewScope $scope): ?array;

    /**
     * Whether the change set is still open, asked in the scope's context and for no user: a
     * preview made for it may still be needed.
     */
    public function isOpen(PreviewScope $scope): bool;
}
