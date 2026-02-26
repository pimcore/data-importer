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

namespace Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse;

use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeysResponse;
use Pimcore\Bundle\StudioBackendBundle\Event\AbstractPreResponseEvent;

final class ClassificationStoreKeysEvent extends AbstractPreResponseEvent
{
    public const string EVENT_NAME = 'pre_response.data_importer.classification_store_keys';

    public function __construct(
        private readonly ClassificationStoreKeysResponse $classificationStoreKeys
    ) {
        parent::__construct($classificationStoreKeys);
    }

    public function getClassificationStoreKeys(): ClassificationStoreKeysResponse
    {
        return $this->classificationStoreKeys;
    }
}
