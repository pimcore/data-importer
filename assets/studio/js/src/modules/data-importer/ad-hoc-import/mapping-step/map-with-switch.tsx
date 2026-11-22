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
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Flex, Segmented, Text } from '@pimcore/studio-ui-bundle/components'
import { type MapWith } from './configuration-state'

// the chosen way stands out from the track: on the container's background, its label bold
const useStyles = createStyles(({ token, css }) => ({
  switch: css`
    .ant-segmented-item-selected {
      background: ${token.colorBgContainer};
      font-weight: ${token.fontWeightStrong};
    }
  `,
  divided: css`
    margin: 0 -${token.paddingLG}px;
    padding: 0 ${token.paddingLG}px ${token.paddingSM}px;
    border-bottom: 1px solid ${token.colorBorderSecondary};
  `
}))

export interface MapWithSwitchProps {
  value: MapWith
  onChange: (mapWith: MapWith) => void
  // a divider across the dialog below the row, where no band follows it
  divided?: boolean
}

export const MapWithSwitch = ({ value, onChange, divided = false }: MapWithSwitchProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  return (
    <Flex
      align="center"
      className={ divided ? styles.divided : undefined }
      gap="normal"
    >
      <Segmented
        className={ styles.switch }
        onChange={ (next) => { onChange(next as MapWith) } }
        options={ [
          { value: 'here', label: t('data-importer.ad-hoc-import.mapping.map-here') },
          { value: 'configuration', label: t('data-importer.ad-hoc-import.mapping.use-configuration') }
        ] }
        value={ value }
      />
      <Text type="secondary">
        { t(value === 'here' ? 'data-importer.ad-hoc-import.mapping.map-here-hint' : 'data-importer.ad-hoc-import.configuration.replaces') }
      </Text>
    </Flex>
  )
}
