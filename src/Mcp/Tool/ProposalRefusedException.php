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

namespace Pimcore\Bundle\DataImporterBundle\Mcp\Tool;

use RuntimeException;

/**
 * A proposal the tool will not record, carrying the sentence the agent is told. The message is
 * read back to a model that will try again, so it says what to send instead.
 *
 * @internal
 */
final class ProposalRefusedException extends RuntimeException
{
}
