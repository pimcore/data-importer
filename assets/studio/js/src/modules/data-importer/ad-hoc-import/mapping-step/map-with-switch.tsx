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
import { Flex, Segmented, Text } from '@pimcore/studio-ui-bundle/components'
import { type MapWith } from './configuration-state'

export interface MapWithSwitchProps {
  value: MapWith
  onChange: (mapWith: MapWith) => void
}

export const MapWithSwitch = ({ value, onChange }: MapWithSwitchProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <Flex
      align="center"
      gap="small"
    >
      <Text>{ t('data-importer.ad-hoc-import.mapping.map-with') }</Text>
      <Segmented
        onChange={ (next) => { onChange(next as MapWith) } }
        options={ [
          { value: 'here', label: t('data-importer.ad-hoc-import.mapping.map-here') },
          { value: 'configuration', label: t('data-importer.ad-hoc-import.mapping.use-configuration') }
        ] }
        value={ value }
      />
    </Flex>
  )
}
