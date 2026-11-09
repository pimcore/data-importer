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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\CsvDelimiterDetector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * The real file store and a CSV-only inspector, as the container wires them.
 */
final class AdHocCsvFiles
{
    public static function store(string $directory, ?RunStoreInterface $runs = null): FileStore
    {
        return new FileStore(
            new Filesystem(new LocalFilesystemAdapter($directory)),
            new LockFactory(new InMemoryStore()),
            $runs
        );
    }

    public static function inspector(): FileInspector
    {
        $factory = new InterpreterFactory([
            'csv' => (new \ReflectionClass(CsvFileInterpreter::class))->newInstanceWithoutConstructor(),
        ]);

        return new FileInspector(new SourceFileReader($factory), $factory, new CsvDelimiterDetector());
    }
}
