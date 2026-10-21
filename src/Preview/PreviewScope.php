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

namespace Pimcore\Bundle\DataImporterBundle\Preview;

/**
 * A preview made while reviewing a change set: the change set, and the context its author
 * keeps it in — an agent's proposals are visible only there.
 *
 * @internal
 */
final readonly class PreviewScope
{
    public function __construct(
        public string $changeSetId,
        public ?string $contextRef = null,
    ) {
    }

    /** request fields arrive as strings, empty when not sent */
    public static function fromRequest(string $changeSetId, string $contextRef): ?self
    {
        if ($changeSetId === '') {
            return null;
        }

        return new self($changeSetId, $contextRef !== '' ? $contextRef : null);
    }
}
