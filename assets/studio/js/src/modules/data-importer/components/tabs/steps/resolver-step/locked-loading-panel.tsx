/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo } from 'react'
import { Form, Text } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel'
import { useBundleDataImporterDataTypeLoadClassAttributesQuery } from '../../../../data-importer-api-slice.gen'
import { parseClassAttribute } from '../mapping-step/hooks/use-mapping-step-loader.types'

interface LoadingStrategy {
  type?: string
  settings?: { dataSourceIndex?: string, attributeName?: string }
}

/**
 * Says how a host that derives Element Loading from its own key finds records, instead of showing fields it may not change.
 */
export const LockedLoadingPanel = (): React.JSX.Element => {
  const { t } = useTranslation()
  const classId = Form.useWatch(['resolverConfig', 'dataObjectClassId']) as string | undefined
  // no field holds these values under the lock, so they are read from the store
  const loading = Form.useWatch(['resolverConfig', 'loadingStrategy'], { preserve: true }) as LoadingStrategy | undefined
  const column = loading?.type === 'attribute' ? loading.settings?.dataSourceIndex : undefined
  const attribute = loading?.type === 'attribute' ? loading.settings?.attributeName : undefined
  const hasKey = column !== undefined && column !== '' && attribute !== undefined && attribute !== ''

  const { data } = useBundleDataImporterDataTypeLoadClassAttributesQuery(
    { classId: classId ?? '' },
    { skip: !hasKey || classId === undefined || classId === '' }
  )
  const field = useMemo(() => {
    const title = (data?.attributes ?? []).map(parseClassAttribute).find(item => item.key === attribute)?.title
    return title === undefined || title === '' || title === attribute ? attribute : `${title} (${attribute})`
  }, [data, attribute])

  return (
    <DataImporterPanel title={ t('data-importer.resolver.element-loading') }>
      <Text>
        { hasKey
          // React escapes the values
          ? t('data-importer.resolver.loading-strategy.locked', { column, field, interpolation: { escapeValue: false } })
          : t('data-importer.resolver.loading-strategy.locked-no-key') }
      </Text>
    </DataImporterPanel>
  )
}
