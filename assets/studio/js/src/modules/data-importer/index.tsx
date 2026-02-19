/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { container, type AbstractModule } from '@pimcore/studio-ui-bundle'
import { type DynamicTypeDataHubAdapterRegistry, bundleServiceIds as dataHubServiceIds } from '@pimcore/data-hub'
import { bundleServiceIds } from '../../config/service-ids'

export const DataImporterModule: AbstractModule = {
  onInit: (): void => {
    const adapterRegistry = container.get<DynamicTypeDataHubAdapterRegistry>(dataHubServiceIds['DataHub/DynamicTypes/Adapter/Registry'])
    adapterRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Adapter/DataImporterDataObject']))
  }
}
