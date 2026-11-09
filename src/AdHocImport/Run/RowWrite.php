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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Run;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;

/**
 * What one row of a run changes, ready to be written.
 *
 * @internal
 */
final readonly class RowWrite
{
    /**
     * @param Concrete $object the object as the mapping and the events left it
     * @param array<int|string, mixed> $row the row as the file has it
     */
    public function __construct(
        public Concrete $object,
        public array $row,
        public bool $isNew,
        public User $user,
    ) {
    }
}
