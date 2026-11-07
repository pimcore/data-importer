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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits;

use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Requires the using class to have a property: DataObjectResolverInterface $dataObjectResolver
 *
 * @internal
 *
 * @property DataObjectResolverInterface $dataObjectResolver
 */
trait TargetFolderTrait
{
    /**
     * @throws NotFoundHttpException if $folderId is not a data object folder
     * @throws ForbiddenException if the user may not create and save in that folder
     */
    private function assertMayImportInto(int $folderId, User $user): Folder
    {
        $folder = $this->dataObjectResolver->getById($folderId);
        if (!$folder instanceof Folder) {
            throw new NotFoundHttpException(sprintf('Data object folder %d not found', $folderId));
        }

        if (!$folder->isAllowed('create', $user) || !$folder->isAllowed('save', $user)) {
            throw new ForbiddenException(sprintf('Access denied to data object folder %d', $folderId));
        }

        return $folder;
    }
}
