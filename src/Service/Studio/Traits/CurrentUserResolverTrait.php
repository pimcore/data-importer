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

use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;

/**
 * Requires the using class to have a property: SecurityServiceInterface $securityService
 *
 * @internal
 *
 * @property SecurityServiceInterface $securityService
 */
trait CurrentUserResolverTrait
{
    /**
     * Resolve the current user, ensuring it is a Pimcore User instance.
     *
     * @throws EnvironmentException if the current user cannot be resolved
     */
    private function resolveCurrentUser(): User
    {
        $user = $this->securityService->getCurrentUser();

        if (!$user instanceof User) {
            throw new EnvironmentException('Could not resolve current user');
        }

        return $user;
    }
}
