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
namespace Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage;

use RuntimeException;

/**
 * @internal
 */
final class RangeLimitExceededException extends RuntimeException
{
    public function __construct(int $maxItems)
    {
        parent::__construct(sprintf('The ranges of an expression can have %d items in total at most', $maxItems));
    }
}
