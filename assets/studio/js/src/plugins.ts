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

if (module.hot !== undefined) {}

export const DataImporterPlugin: IAbstractPlugin = {
  name: 'data-importer-plugin',

  // Register and overwrite services here
  onInit: ({ container }): void => {},

  // register modules here
  onStartup: ({ moduleSystem }): void => {
    moduleSystem.registerModule(DataImporterModule)
    console.log('Hello from data importer bundle.')
  }
}
