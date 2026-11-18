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

namespace Pimcore\Bundle\DataImporterBundle\Tests\Fixture;

use Doctrine\DBAL\Connection;
use Pimcore\Bundle\DataImporterBundle\Resolver\Factory\DataObjectFactory;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\AttributeStrategy;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\IdStrategy;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\NotLoadStrategy;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\NoChangeStrategy;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\StaticPathStrategy;
use Pimcore\Bundle\DataImporterBundle\Resolver\Publish\AlwaysPublishStrategy;
use Pimcore\Bundle\DataImporterBundle\Resolver\Publish\NoChangeUnpublishNewStrategy;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;

/**
 * The importer's resolver factory with some of its strategies, as the container holds them.
 */
final class ResolverBlueprints
{
    public static function resolvers(): ResolverFactory
    {
        return new ResolverFactory(
            new Resolver(),
            [
                'id' => ImporterBlueprints::blueprint(IdStrategy::class),
                'attribute' => new AttributeStrategy(
                    ImporterBlueprints::blueprint(Connection::class),
                    new DataObjectLoader(),
                ),
                'notLoad' => new NotLoadStrategy(),
            ],
            ['staticPath' => new StaticPathStrategy(), 'noChange' => new NoChangeStrategy()],
            [
                'alwaysPublish' => new AlwaysPublishStrategy(),
                'noChangeUnpublishNew' => new NoChangeUnpublishNewStrategy(),
            ],
            ['dataObject' => ImporterBlueprints::blueprint(DataObjectFactory::class)],
        );
    }
}
