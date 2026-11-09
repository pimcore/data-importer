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

namespace Pimcore\Bundle\DataImporterBundle\Schema;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What to import a file by: what its dry run was asked with and what becomes of the rows with errors.
 *
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportRunParameters',
    title: 'Bundle Data Importer Ad Hoc Import Run Parameters',
    required: ['settings', 'errorMode', 'fileName'],
    type: 'object'
)]
final readonly class AdHocImportRunParameters
{
    public function __construct(
        #[Property(description: 'What the file was checked with', ref: AdHocImportDryRunParameters::class)]
        #[Assert\Valid]
        private AdHocImportDryRunParameters $settings,
        #[Property(
            description: 'skip: leave out every row with an error; values: import a row without its invalid values',
            type: 'string',
            enum: [
                AdHocImportDryRunServiceInterface::SKIP_ROWS,
                AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES,
            ],
            example: AdHocImportDryRunServiceInterface::SKIP_ROWS
        )]
        #[Assert\Choice([
            AdHocImportDryRunServiceInterface::SKIP_ROWS,
            AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES,
        ])]
        private string $errorMode,
        #[Property(description: 'The name of the file, as the result names it', type: 'string', example: 'autumn.xlsx')]
        #[Assert\Length(max: 255)]
        private string $fileName,
    ) {
    }

    public function getSettings(): AdHocImportDryRunParameters
    {
        return $this->settings;
    }

    public function getErrorMode(): string
    {
        return $this->errorMode;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }
}
