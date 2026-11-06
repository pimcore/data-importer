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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Apply;

use Pimcore\Model\Element\ElementInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
trait MappingApplicationScopeAwareTrait
{
    private ?MappingApplicationScope $mappingApplicationScope = null;

    #[Required]
    public function setMappingApplicationScope(MappingApplicationScope $mappingApplicationScope): void
    {
        $this->mappingApplicationScope = $mappingApplicationScope;
    }

    private function isAppliedWithoutSaving(): bool
    {
        return $this->mappingApplicationScope?->isActive() ?? false;
    }

    /**
     * Nothing is logged while a mapping is applied without saving; the caller gets the warning from apply() instead.
     *
     * @return bool false if the warning still has to be logged
     */
    private function reportWarningIfAppliedWithoutSaving(string $message): bool
    {
        if (!$this->isAppliedWithoutSaving()) {
            return false;
        }

        $this->mappingApplicationScope?->addWarning($message);

        return true;
    }

    private function hasReferenceLookup(): bool
    {
        return $this->mappingApplicationScope?->getReferenceLookup() !== null;
    }

    /**
     * @template T of ElementInterface
     *
     * @param class-string<T> $expectedClass
     *
     * @return T|null
     */
    private function lookupReference(ReferenceQuery $query, string $expectedClass): ?ElementInterface
    {
        $element = $this->mappingApplicationScope?->getReferenceLookup()?->find($query);
        if ($element === null || $element instanceof $expectedClass) {
            return $element;
        }

        throw new \UnexpectedValueException(sprintf(
            'Reference lookup returned %s for %s reference `%s`.',
            get_debug_type($element),
            $query->type->value,
            $query->key
        ));
    }
}
