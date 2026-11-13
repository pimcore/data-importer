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

namespace Pimcore\Bundle\DataImporterBundle\ChangeControl;

use ErrorException;

/**
 * A PHP warning or notice raised while a loader reads proposed settings, turned into an exception
 * so the check can name it.
 *
 * @internal
 */
final class LoaderSettingsReadException extends ErrorException
{
}
