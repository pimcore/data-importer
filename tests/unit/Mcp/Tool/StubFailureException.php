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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Mcp\Tool;

use RuntimeException;

/**
 * Dedicated exception the MCP tool tests throw from stubbed collaborators to simulate an
 * unexpected runtime failure, so the assertions exercise the tools' generic error handling
 * without tripping SonarCloud's "throw a dedicated exception" rule (S112) on a generic
 * \Exception / \RuntimeException.
 *
 * @internal
 */
final class StubFailureException extends RuntimeException
{
}
