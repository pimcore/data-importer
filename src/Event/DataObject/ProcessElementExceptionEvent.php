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

namespace Pimcore\Bundle\DataImporterBundle\Event\DataObject;

use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Model\Element\ElementInterface;
use Throwable;

class ProcessElementExceptionEvent extends AbstractDataObjectImportEvent
{
    public function __construct(
        string $configName,
        array $rawData,
        ElementInterface $dataObject,
        private ?string $message,
        private Throwable $exception,
        private ?MappingConfiguration $mappingConfiguration
    ) {
        parent::__construct($configName, $rawData, $dataObject);
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * This is the last mapping configuration that was being processed when the exception was thrown. No value will be set if all transformations were completed.
     * @return null|MappingConfiguration 
     */
    public function getMappingConfiguration(): ?MappingConfiguration
    {
        return $this->mappingConfiguration;
    }
}
