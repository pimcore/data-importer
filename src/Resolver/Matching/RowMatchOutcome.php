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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Matching;

/**
 * What one row of a file resolves to. The first two are rows that can be imported, every other one is an error row.
 *
 * @internal
 */
enum RowMatchOutcome: string
{
    case Existing = 'existing';
    case New = 'new';

    // no value to match by, and so no key for a new object
    case EmptyValue = 'empty_value';

    // an earlier row of the file has the same value or resolved to the same object
    case DuplicateInFile = 'duplicate_in_file';
    case SeveralMatches = 'several_matches';
    case OutsideTargetFolder = 'outside_target_folder';

    // the Object ID belongs to a folder or an object of another class
    case OtherClass = 'other_class';

    // something else already has the path a new object would get, or an earlier new object of the file claimed it
    case KeyTaken = 'key_taken';
}
