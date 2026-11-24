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
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterLoadPreviewParameters',
    title: 'Bundle Data Importer Load Preview Parameters',
    type: 'object'
)]
final readonly class LoadPreviewParameters
{
    public function __construct(
        #[Property(
            description: 'Optional unsaved in-progress configuration from the UI. When provided, '
                . 'the interpreter and mapping use these settings instead of the saved configuration.',
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
            description: 'Zero-based record number to preview from the data source',
            type: 'integer',
            example: 0
        )]
        private int $recordNumber = 0,
        #[Property(
            description: 'Change set under review (UUID): previews are kept apart '
                . 'and a proposed-only config is accepted',
            type: 'string',
            example: '01930f5e-7a3c-7b2d-9e4f-1a2b3c4d5e6f',
        )]
        // a plain string, not ?string: the Studio codegen turns a nullable into `any`
        private string $previewScope = '',
        #[Property(
            description: 'The context the change set is kept in, when its author keeps it in one',
            type: 'string',
            example: 'author-context-1',
        )]
        private string $previewContext = '',
    ) {
    }

    public function getCurrentConfig(): ?array
    {
        return $this->currentConfig;
    }

    public function getRecordNumber(): int
    {
        return $this->recordNumber;
    }

    public function getPreviewScope(): ?PreviewScope
    {
        return PreviewScope::fromRequest($this->previewScope, $this->previewContext);
    }
}
