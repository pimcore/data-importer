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
use Pimcore\Bundle\DataImporterBundle\Schema\ConfigurationDetail;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ConflictException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
interface ConfigurationServiceInterface
{
    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     */
    public function getConfiguration(string $name): ConfigurationDetail;

    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     * @throws ConflictException
     * @throws Exception
     */
    public function saveConfiguration(string $name, array $configuration, int $modificationDate): ConfigurationDetail;
}
