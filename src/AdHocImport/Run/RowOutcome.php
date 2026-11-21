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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Run;

/**
 * What became of one row of a run.
 *
 * @internal
 */
final readonly class RowOutcome
{
    public const string UPDATED = 'updated';

    public const string CREATED = 'created';

    public const string FAILED = 'failed';

    /**
     * @param string|null $action update or create, as far as the row got; null if it found no object
     * @param string|null $message why the row was not saved
     * @param int|null $objectId the object saved; null while a branch holds a new one
     */
    private function __construct(
        public string $result,
        public string $matchValue,
        public ?string $action,
        public ?string $message,
        public ?int $objectId = null,
    ) {
    }

    public static function saved(bool $isNew, string $matchValue, ?int $objectId = null): self
    {
        return new self(
            $isNew ? self::CREATED : self::UPDATED,
            $matchValue,
            $isNew ? 'create' : 'update',
            null,
            $objectId
        );
    }

    public static function failed(string $matchValue, ?string $action, string $message): self
    {
        return new self(self::FAILED, $matchValue, $action, $message);
    }

    public function isFailure(): bool
    {
        return $this->result === self::FAILED;
    }
}
