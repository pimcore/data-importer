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

use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsArray;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Boolean;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Date;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Gallery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Numeric;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\ConditionalConversion;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\Explode;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\LoadAsset;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\LoadDataObject;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\StringReplace;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\Trim;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;

/**
 * The transformation chain with the operators the container registers for an ad hoc import.
 */
final class AdHocTransformations
{
    /**
     * @param list<string> $without operator types to leave out, as if no bundle registered them
     */
    public static function chain(DataTypeServiceInterface $types, array $without = []): TransformationChain
    {
        // the operators log nothing in a dry run
        $logger = (new \ReflectionClass(ApplicationLogger::class))->newInstanceWithoutConstructor();

        $operators = array_diff_key([
            'trim' => new Trim($logger),
            'stringReplace' => new StringReplace($logger),
            'conditionalConversion' => new ConditionalConversion($logger),
            'explode' => new Explode($logger),
            'loadAsset' => new LoadAsset($logger),
            'loadDataObject' => new LoadDataObject($logger),
            'numeric' => new Numeric($logger),
            'date' => new Date($logger),
            'asArray' => new AsArray($logger),
            'gallery' => new Gallery($logger),
            'boolean' => new Boolean($logger),
        ], array_flip($without));

        return new TransformationChain(new MappingConfigurationFactory(new MappingConfiguration(), $operators, []), $types);
    }
}
