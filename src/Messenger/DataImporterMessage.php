<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Messenger;

class DataImporterMessage
{
    public function __construct(protected string $executionType, protected array $ids, protected string $messageId)
    {
    }

    public function getExecutionType(): string
    {
        return $this->executionType;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }
}
