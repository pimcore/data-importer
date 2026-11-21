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

use Pimcore\Bundle\DataImporterBundle\Schema\ImportProgressResponse;
use Pimcore\Bundle\StudioBackendBundle\Event\AbstractPreResponseEvent;

final class ImportProgressEvent extends AbstractPreResponseEvent
{
    public const string EVENT_NAME = 'pre_response.data_importer.import_progress';

    public function __construct(
        private readonly ImportProgressResponse $importProgress
    ) {
        parent::__construct($importProgress);
    }

    public function getImportProgress(): ImportProgressResponse
    {
        return $this->importProgress;
    }
}
