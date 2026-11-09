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

namespace Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough;

final readonly class WalkthroughStep
{
    /**
     * @param list<string> $lines what happened and what was read back
     * @param list<string> $failed the checks that did not hold
     */
    public function __construct(
        public int $number,
        public string $title,
        public array $lines,
        public array $failed,
    ) {
    }

    public function passed(): bool
    {
        return $this->failed === [];
    }

    public function render(): string
    {
        $out = sprintf("%d %s\n", $this->number, $this->title);
        foreach ($this->lines as $line) {
            $out .= '  ' . $line . "\n";
        }
        foreach ($this->failed as $line) {
            $out .= '  FAILED: ' . $line . "\n";
        }

        return $out . ($this->passed() ? 'PASS' : 'FAIL') . "\n";
    }
}
