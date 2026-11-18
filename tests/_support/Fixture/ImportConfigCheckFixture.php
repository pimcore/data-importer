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

use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigCheck;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\LoaderSettingsCheck;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\AssetLoader;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\HttpLoader;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Objectbrick;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * An ImportConfigCheck built from the importer's own components, with an in-memory target class,
 * so the checks run without a container or stored class definitions.
 */
final class ImportConfigCheckFixture
{
    public const string CLASS_ID = 'CAR';

    public const string CLASS_NAME = 'DiConfigCheckCar';

    public const string BRICK_NAME = 'DiConfigCheckEngine';

    /**
     * @param array<string, callable> $loaders loader type => factory, besides asset and http
     */
    public static function check(array $loaders = []): ImportConfigCheck
    {
        self::registerClass();

        return new ImportConfigCheck(
            new LoaderSettingsCheck(new ServiceLocator($loaders + [
                'asset' => static fn (): AssetLoader => new AssetLoader(),
                'http' => static fn (): HttpLoader => new HttpLoader(new Filesystem()),
            ])),
            ImporterBlueprints::interpreters(),
            ResolverBlueprints::resolvers(),
            ImporterBlueprints::mappings(),
            ImporterBlueprints::cleanupStrategies(),
        );
    }

    /**
     * Drops the class and the brick check() registered.
     */
    public static function forget(): void
    {
        foreach (['class_' . self::CLASS_ID, 'objectbrick_' . self::BRICK_NAME] as $key) {
            if (RuntimeCache::isRegistered($key)) {
                RuntimeCache::getInstance()->offsetUnset($key);
            }
        }
    }

    /**
     * A class with an own field, a localized one, an object brick and a classification store.
     */
    private static function registerClass(): void
    {
        // an object brick field keeps only the bricks it can find
        $brick = new Objectbrick\Definition();
        $brick->setKey(self::BRICK_NAME);
        $brick->setFieldDefinitions(['power' => self::input('power')]);
        RuntimeCache::set('objectbrick_' . self::BRICK_NAME, $brick);

        $localized = new Data\Localizedfields();
        $localized->setName('localizedfields');
        $localized->setChildren([self::input('description')]);

        $bricks = new Data\Objectbricks();
        $bricks->setName('bricks');
        $bricks->setAllowedTypes([self::BRICK_NAME]);

        $store = new Data\Classificationstore();
        $store->setName('attributes');

        $class = new ClassDefinition();
        $class->setId(self::CLASS_ID);
        $class->setName(self::CLASS_NAME);
        $class->setFieldDefinitions([
            'vin' => self::input('vin'),
            'name' => self::input('name'),
            'localizedfields' => $localized,
            'bricks' => $bricks,
            'attributes' => $store,
        ]);
        RuntimeCache::set('class_' . self::CLASS_ID, $class);
    }

    private static function input(string $name): Data\Input
    {
        $input = new Data\Input();
        $input->setName($name);

        return $input;
    }
}
