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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch;

/**
 * The object a row updates was deleted after the row was read.
 *
 * @internal
 */
final class ObjectGoneException extends \RuntimeException
{
    public static function of(int $id): self
    {
        return new self(sprintf('Object %d was deleted meanwhile.', $id));
    }
}
