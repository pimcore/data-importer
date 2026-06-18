<?php

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
 * @internal
 */
final class PermissionConstants
{
    public const string PLUGIN_DATA_IMPORTER_CONFIG = 'plugin_datahub_config';

    // Per-configuration permission types as evaluated by Configuration::isAllowed() against the
    // per-config permission grid (must match the grid keys: read/update/delete).
    public const string PLUGIN_DATA_IMPORTER_PERMISSION_READ = 'read';

    public const string PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE = 'update';

    public const string PLUGIN_DATA_IMPORTER_PERMISSION_DELETE = 'delete';

    public const string PLUGIN_DATA_IMPORTER_ADMIN = 'plugin_datahub_admin';
}
