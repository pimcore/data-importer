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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Type;

use Pimcore\Model\DataObject\Classificationstore\Engine\EngineFeature;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineInterface;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineResolver;
use Pimcore\Model\DataObject\Classificationstore\KeyTypeSupport;

/**
 * What the engine behind a classification store can hold, asked of core rather than listed here.
 *
 * The same question {@see ClassificationStoreDataTypeService} asks of every key shape of a store at
 * once, to build the key listing, answered for one shape so that a write can ask it per key.
 *
 * @internal
 */
final class ClassificationStoreEngineSupport
{
    public function __construct(
        private readonly KeyTypeSupport $keyTypeSupport,
        private readonly EngineResolver $engineResolver
    ) {
    }

    /**
     * Whether the engine behind the store can hold a value of this key shape. An engine that offers
     * structured values holds every type, so only the other kind is asked about a shape at all.
     *
     * A shape is a type and the delegate it wraps, because that is what core is asked: a
     * `multiValue` key is held exactly when its delegate is, so the type alone would answer "held"
     * for a `multiValue` over a `geopoint` on an EAV store.
     *
     * Deliberately not {@see KeyTypeSupport::requiredEngineFeatures()}, which answers the other
     * half - the features the DECLARATION of a key needs. A declaration an engine cannot address is
     * answered where a qualified value is written, by writing the default variant and warning about
     * it ({@see \Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreKeyTrait}),
     * and asking it here would drop that value instead of degrading it.
     */
    public function canHoldKeyType(int $storeId, string $type, ?string $delegateType = null): bool
    {
        if ($this->resolveEngine($storeId)->supports(EngineFeature::StructuredValues)) {
            return true;
        }

        return $this->keyTypeSupport->isHeldInValueColumns($type, $delegateType);
    }

    /**
     * The name the engine of the store is registered under, for a message that has to name it.
     */
    public function engineName(int $storeId): string
    {
        return $this->engineResolver->nameEngine($this->resolveEngine($storeId));
    }

    private function resolveEngine(int $storeId): EngineInterface
    {
        return $this->engineResolver->resolveForStore($storeId);
    }
}
