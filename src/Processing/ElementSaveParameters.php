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

namespace Pimcore\Bundle\DataImporterBundle\Processing;

/**
 * @internal
 */
final class ElementSaveParameters
{
    /**
     * @param array<string, mixed> $processingConfig
     *
     * @return array<string, string> parameters for ElementInterface::save()
     */
    public static function fromProcessingConfig(array $processingConfig): array
    {
        $versionNote = $processingConfig['versionNote'] ?? null;
        if (!is_string($versionNote)) {
            return [];
        }

        $versionNote = trim($versionNote);
        if ($versionNote === '') {
            return [];
        }

        return ['versionNote' => $versionNote];
    }
}
