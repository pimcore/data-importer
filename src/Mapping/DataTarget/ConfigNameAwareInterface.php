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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

/**
 * A data target that wants the name of the configuration it runs for, which is what its log lines are filed under.
 *
 * @internal
 */
interface ConfigNameAwareInterface
{
    public function setConfigName(string $configName): void;
}
