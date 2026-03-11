/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type IAbstractPlugin } from '@pimcore/studio-ui-bundle'
import { DataImporterModule } from './modules/data-importer/index'
import { bundleServiceIds } from './config/service-ids'
import { DynamicTypeDataImporterDataObject } from './modules/data-importer/dynamic-types/dynamic-type-data-importer-data-object'

if (module.hot !== undefined) {
  module.hot.accept()
}

export const DataImporterPlugin: IAbstractPlugin = {
  name: 'data-importer-plugin',

  // Register and overwrite services here
  onInit: ({ container }): void => {
    container.bind(String(bundleServiceIds['DataImporter/DynamicTypes/Adapter/DataImporterDataObject'])).to(DynamicTypeDataImporterDataObject).inSingletonScope()
  },

  // register modules here
  onStartup: ({ moduleSystem }): void => {
    moduleSystem.registerModule(DataImporterModule)
    console.log('Hello from data importer bundle.')
  }
}
