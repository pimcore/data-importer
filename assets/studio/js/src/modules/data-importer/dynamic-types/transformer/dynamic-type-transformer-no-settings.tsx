/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

/**
 * All transformer types that have no configurable settings.
 * They are grouped here to avoid creating one tiny file per type.
 */

import React from 'react'
import { injectable } from '@pimcore/studio-ui-bundle/app'
import { Text } from '@pimcore/studio-ui-bundle/components'
import { DynamicTypeTransformerAbstract, type TransformerGroup } from './dynamic-type-transformer-abstract'
import { useTransformerTypeStyles } from './transformer-type.styles'

const NoSettingsContent = (): React.JSX.Element => {
  const { styles } = useTransformerTypeStyles()
  return (
    <Text
      className={ styles.noSettings }
      type="secondary"
    >No additional settings</Text>
  )
}

function makeNoSettings (id: string, label: string, group: TransformerGroup): new () => DynamicTypeTransformerAbstract {
  @injectable()
  class NoSettingsType extends DynamicTypeTransformerAbstract {
    readonly id = id
    readonly label = label
    readonly group = group

    renderSettings (): React.JSX.Element | null {
      return <NoSettingsContent />
    }
  }
  return NoSettingsType
}

// dataManipulation
export const DynamicTypeTransformerFlattenArray = makeNoSettings('flattenArray', 'Flatten Array', 'dataManipulation')
export const DynamicTypeTransformerReduceArrayKeyValuePairs = makeNoSettings('reduceArrayKeyValuePairs', 'Reduce Array Key-Value Pairs', 'dataManipulation')
export const DynamicTypeTransformerHtmlDecode = makeNoSettings('htmlDecode', 'HTML Decode', 'dataManipulation')

// dataTypes
export const DynamicTypeTransformerBoolean = makeNoSettings('boolean', 'Boolean', 'dataTypes')
export const DynamicTypeTransformerAsArray = makeNoSettings('asArray', 'As Array', 'dataTypes')
export const DynamicTypeTransformerAsColor = makeNoSettings('asColor', 'As Color', 'dataTypes')
export const DynamicTypeTransformerAsCountries = makeNoSettings('asCountries', 'As Countries', 'dataTypes')
export const DynamicTypeTransformerGallery = makeNoSettings('gallery', 'Gallery', 'dataTypes')
export const DynamicTypeTransformerImageAdvanced = makeNoSettings('imageAdvanced', 'Image Advanced', 'dataTypes')
export const DynamicTypeTransformerQuantityValueArray = makeNoSettings('quantityValueArray', 'Quantity Value Array', 'dataTypes')
export const DynamicTypeTransformerInputQuantityValue = makeNoSettings('inputQuantityValue', 'Input Quantity Value', 'dataTypes')
export const DynamicTypeTransformerInputQuantityValueArray = makeNoSettings('inputQuantityValueArray', 'Input Quantity Value Array', 'dataTypes')
export const DynamicTypeTransformerAsGeobounds = makeNoSettings('asGeobounds', 'As Geobounds', 'dataTypes')
export const DynamicTypeTransformerAsGeopoint = makeNoSettings('asGeopoint', 'As Geopoint', 'dataTypes')
export const DynamicTypeTransformerAsGeopolygon = makeNoSettings('asGeopolygon', 'As Geopolygon', 'dataTypes')
export const DynamicTypeTransformerAsGeopolyline = makeNoSettings('asGeopolyline', 'As Geopolyline', 'dataTypes')
