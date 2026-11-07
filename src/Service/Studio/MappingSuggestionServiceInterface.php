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
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionsResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
interface MappingSuggestionServiceInterface
{
    /**
     * Answers with the name comparison's suggestions at once; with the AI stage on, its task starts in the background.
     *
     * @throws ForbiddenException without the data importer's configuration right or the ad hoc import's right,
     *                             or with only the latter for a class the user may not create
     * @throws NotFoundException when the class does not exist
     * @throws NotFoundHttpException when sample values are on and the named configuration does not exist
     * @throws ForbiddenException when sample values are on and the user may not read the named configuration
     * @throws Exception
     */
    public function suggest(MappingSuggestionParameters $parameters): MappingSuggestionsResponse;

    /**
     * Where the AI stage of an earlier suggest() stands, with its suggestions once it finished.
     *
     * @throws NotFoundException when the request is unknown or was started by another user
     * @throws EnvironmentException when the current user cannot be resolved
     */
    public function loadAi(string $requestId): MappingSuggestionsResponse;
}
