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
use Pimcore\Bundle\StudioBackendBundle\Util\Schema\AdditionalAttributesInterface;
use Pimcore\Bundle\StudioBackendBundle\Util\Trait\AdditionalAttributesTrait;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterImportFileStatusResponse',
    title: 'Bundle Data Importer Import File Status Response',
    required: ['exists', 'message'],
    type: 'object'
)]
final class ImportFileStatusResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    public function __construct(
        #[Property(
            description: 'Whether the import file has been uploaded',
            type: 'boolean',
            example: true
        )]
        private readonly bool $exists,
        #[Property(
            description: 'Status message about the import file',
            type: 'string',
            example: 'Upload file already exists'
        )]
        private readonly string $message,
        #[Property(
            description: 'Path of the import file in storage (only when exists is true)',
            type: 'string',
            example: 'my-config/upload.import',
            nullable: true
        )]
        private readonly ?string $filePath = null,
    ) {
    }

    public function isExists(): bool
    {
        return $this->exists;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
}
