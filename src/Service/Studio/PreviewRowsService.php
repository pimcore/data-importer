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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\ConfigurationPermissionTrait;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;

/**
 * @internal
 */
final readonly class PreviewRowsService implements PreviewRowsServiceInterface
{
    use ConfigurationPermissionTrait;
    use CurrentUserResolverTrait;

    public function __construct(
        private PreviewService $previewService,
        private SecurityServiceInterface $securityService,
        private ConfigurationPreparationService $configurationPreparationService,
        private PreviewEventApplier $previewEventApplier,
        private SourceFileReader $sourceFileReader
    ) {
    }

    public function read(string $configName, ?array $currentConfig): iterable
    {
        $this->loadConfigurationWithPermission($configName, PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ);

        $path = $this->previewService->getLocalPreviewFile($configName, $this->resolveCurrentUser());
        if ($path === null) {
            return [];
        }

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration($configName, $currentConfig);

        return $this->sourceFileReader->readRows(
            $preparedConfig['interpreterConfig'],
            $this->previewEventApplier->applyToPath($configName, $preparedConfig['processingConfig'], $path)
        );
    }
}
