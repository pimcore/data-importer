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

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterCopyPreviewParameters',
    title: 'Bundle Data Importer Copy Preview Parameters',
    type: 'object'
)]
final readonly class CopyPreviewParameters
{
    public function __construct(
        #[Property(
            description: 'Optional unsaved in-progress configuration from the UI. When provided, the loader uses these settings instead of the saved configuration.',
            properties: [
                new Property(property: 'general', type: 'object'),
                new Property(property: 'loaderConfig', type: 'object'),
                new Property(property: 'interpreterConfig', type: 'object'),
                new Property(property: 'resolverConfig', type: 'object'),
                new Property(property: 'processingConfig', type: 'object'),
                new Property(property: 'mappingConfig', type: 'object'),
                new Property(property: 'executionConfig', type: 'object'),
            ],
            type: 'object',
            nullable: true
        )]
        private ?array $currentConfig = null,
        #[Property(
            description: 'A change set under review: its preview data is kept beside the live configuration\'s, and a configuration that is only proposed so far is accepted',
            type: 'string',
        )]
        // a plain string, not ?string: the Studio codegen turns a nullable into `any`
        private string $previewScope = '',
    ) {
    }

    public function getCurrentConfig(): ?array
    {
        return $this->currentConfig;
    }

    public function getPreviewScope(): ?string
    {
        return $this->previewScope !== '' ? $this->previewScope : null;
    }
}
