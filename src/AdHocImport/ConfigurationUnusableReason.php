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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

/**
 * Why an ad hoc import cannot use an import configuration.
 *
 * @internal
 */
enum ConfigurationUnusableReason: string
{
    case ClassNotCreatable = 'class';
    case Matching = 'matching';
    case Folder = 'folder';
    case FolderMissing = 'folder-missing';
    case FolderNotAllowed = 'folder-not-allowed';
}
