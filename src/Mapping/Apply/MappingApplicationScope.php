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

use Symfony\Contracts\Service\ResetInterface;

/**
 * Tells operators that a mapping is being applied without saving, which reference lookup the caller provided, and
 * collects the warnings they would log during an import.
 *
 * @internal
 */
final class MappingApplicationScope implements ResetInterface
{
    private bool $active = false;

    private ?ReferenceLookupInterface $referenceLookup = null;

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function run(?ReferenceLookupInterface $referenceLookup, callable $callback): mixed
    {
        $previousActive = $this->active;
        $previousReferenceLookup = $this->referenceLookup;
        $previousWarnings = $this->warnings;
        $this->active = true;
        $this->referenceLookup = $referenceLookup;
        $this->warnings = [];

        try {
            return $callback();
        } finally {
            // restore instead of clear, so a nested run leaves the outer one intact
            $this->active = $previousActive;
            $this->referenceLookup = $previousReferenceLookup;
            $this->warnings = $previousWarnings;
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getReferenceLookup(): ?ReferenceLookupInterface
    {
        return $this->active ? $this->referenceLookup : null;
    }

    public function addWarning(string $message): void
    {
        if ($this->active) {
            $this->warnings[] = $message;
        }
    }

    /**
     * Returns the warnings added since the last call and forgets them.
     *
     * @return list<string>
     */
    public function takeWarnings(): array
    {
        $warnings = $this->warnings;
        $this->warnings = [];

        return $warnings;
    }

    public function reset(): void
    {
        $this->active = false;
        $this->referenceLookup = null;
        $this->warnings = [];
    }
}
