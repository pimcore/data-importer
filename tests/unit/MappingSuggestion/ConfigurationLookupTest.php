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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\MappingSuggestion;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\ConfigurationLookup;

class ConfigurationLookupTest extends Unit
{
    public function testAnUnknownConfigurationIsNotFound(): void
    {
        $this->assertNull((new ConfigurationLookup())->getByName('no-such-configuration'));
    }
}
