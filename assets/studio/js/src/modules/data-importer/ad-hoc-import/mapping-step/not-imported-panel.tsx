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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Button, Flex, FormKit, Text } from '@pimcore/studio-ui-bundle/components'
import { type MappingRow } from './mapping-state'

export interface NotImportedPanelProps {
  skipped: MappingRow[]
  onRestore: (column: string) => void
}

export const NotImportedPanel = ({ skipped, onRestore }: NotImportedPanelProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <FormKit.Panel title={ t('data-importer.ad-hoc-import.mapping.not-imported', { count: skipped.length }) }>
      { skipped.map(({ column, label }) => (
        <Flex
          align="center"
          gap="small"
          key={ column }
        >
          <Text>{ label }</Text>
          <Button
            aria-label={ t('data-importer.ad-hoc-import.mapping.restore-column', {
              column: label,
              interpolation: { escapeValue: false }
            }) }
            onClick={ () => { onRestore(column) } }
            type="link"
          >
            { t('data-importer.ad-hoc-import.mapping.restore') }
          </Button>
        </Flex>
      )) }
    </FormKit.Panel>
  )
}
