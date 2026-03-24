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
    schema: 'BundleDataImporterCronValidationResponse',
    title: 'Bundle Data Importer Cron Validation Response',
    required: ['isValid', 'message'],
    type: 'object'
)]
final class CronValidationResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    public function __construct(
        #[Property(
            description: 'Whether the cron expression is valid',
            type: 'boolean',
            example: true
        )]
        private readonly bool $isValid,
        #[Property(
            description: 'Error message if the cron expression is invalid, empty string otherwise',
            type: 'string',
            example: ''
        )]
        private readonly string $message,
    ) {
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
