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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Exception;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
interface PreviewRowsServiceInterface
{
    /**
     * The rows of the current user's preview file of a configuration, keyed as an import would; none without a file
     * and none from a workbook too large to load safely.
     *
     * @param array<string, mixed>|null $currentConfig the unsaved configuration, if any
     *
     * @return iterable<array<int|string, mixed>>
     *
     * @throws NotFoundHttpException when the configuration does not exist
     * @throws ForbiddenException when the user may not read the configuration
     * @throws InvalidConfigurationException when the file format cannot read rows
     * @throws InvalidInputException when the file cannot be read; also thrown while the rows are iterated
     * @throws Exception
     */
    public function read(string $configName, ?array $currentConfig): iterable;
}
