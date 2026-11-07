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
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;

/**
 * @internal
 */
interface MappingSuggestionServiceInterface
{
    /**
     * @throws NotFoundException when the class does not exist
     * @throws Exception
     */
    public function suggest(MappingSuggestionParameters $parameters): MappingSuggestionsResponse;

    /**
     * The fields of an existing class a suggestion can name, each with the result type that writes it.
     *
     * @return list<array{key: string, title: string, localized: bool, transformationResultType: string}>
     */
    public function loadTargets(string $classId): array;
}
