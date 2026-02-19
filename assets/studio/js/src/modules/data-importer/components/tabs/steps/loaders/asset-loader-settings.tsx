/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { Form, ManyToOneRelation } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'

export const AssetLoaderSettings = (): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <Form.Item
      label={ t('data-importer.loader.asset.asset-path') }
      name={ ['loaderConfig', 'settings', 'assetPath'] }
      required
    >
      <ManyToOneRelation
        allowPathTextInput
        assetsAllowed
      />
    </Form.Item>
  )
}
