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
     * Answers with the name comparison's suggestions at once; with the AI stage on and asked for, its task starts in
     * the background.
     *
     * @param list<string> $excludedTargets fields left out before matching, so a column can still get another one
     * @param bool $withAiStage false for a caller that never polls the AI stage's answer
     *
     * @throws ForbiddenException without the data importer's configuration right or the ad hoc import's right,
     *                             or with only the latter for a class the user may not create
     * @throws NotFoundException when the class does not exist
     * @throws NotFoundHttpException when sample values are on and the named configuration does not exist
     * @throws ForbiddenException when sample values are on and the user may not read the named configuration
     * @throws Exception
     */
    public function suggest(
        MappingSuggestionParameters $parameters,
        array $excludedTargets = [],
        bool $withAiStage = true
    ): MappingSuggestionsResponse;

    /**
     * Where the AI stage of an earlier suggest() stands, with its suggestions once it finished.
     *
     * @throws ForbiddenException without the data importer's configuration right or the ad hoc import's right
     * @throws NotFoundException when the request is unknown or was started by another user
     * @throws EnvironmentException when the current user cannot be resolved
     */
    public function loadAi(string $requestId): MappingSuggestionsResponse;

    /**
     * The fields of an existing class a suggestion can name, each with the result type that writes it.
     *
     * @return list<array{key: string, title: string, localized: bool, transformationResultType: string}>
     */
    public function loadTargets(string $classId): array;
}
