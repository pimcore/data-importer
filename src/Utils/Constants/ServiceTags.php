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

namespace Pimcore\Bundle\DataImporterBundle\Utils\Constants;

/**
 * Service tags of the bundle's extension points.
 *
 * Declared once here so the compiler passes that build the configuration factories and the
 * passes that read the same tags do not each repeat the literal.
 *
 * @internal
 */
final class ServiceTags
{
    public const string LOADER = 'pimcore.datahub.data_importer.loader';

    public const string INTERPRETER = 'pimcore.datahub.data_importer.interpreter';

    public const string OPERATOR = 'pimcore.datahub.data_importer.operator';

    public const string DATA_TARGET = 'pimcore.datahub.data_importer.data_target';

    public const string RESOLVER_LOAD = 'pimcore.datahub.data_importer.resolver.load';

    public const string RESOLVER_LOCATION = 'pimcore.datahub.data_importer.resolver.location';

    public const string RESOLVER_PUBLISH = 'pimcore.datahub.data_importer.resolver.publish';

    public const string RESOLVER_FACTORY = 'pimcore.datahub.data_importer.resolver.factory';

    public const string CLEANUP = 'pimcore.datahub.data_importer.cleanup';
}
