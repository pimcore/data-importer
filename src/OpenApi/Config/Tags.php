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

namespace Pimcore\Bundle\DataImporterBundle\OpenApi\Config;

use OpenApi\Attributes\Tag;

#[Tag(
    name: Tags::DataImporter->value,
    description: 'bundle_tag_data_importer_description',
)]
/**
 * @internal
 */
enum Tags: string
{
    case DataImporter = 'Bundle Data Importer';
}
