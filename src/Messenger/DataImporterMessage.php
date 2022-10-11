<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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
