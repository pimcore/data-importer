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
import { Flex, Form, Input, Segmented, Select, Text } from '@pimcore/studio-ui-bundle/components'
import { type TransformationStep } from './mapping-state'

const TEXT_WIDTH = 200

// what a step starts with; the server knows the same operators and settings
export const DEFAULT_SETTINGS: Record<string, Record<string, string>> = {
  trim: { mode: 'both' },
  stringReplace: { search: '', replace: '' },
  conditionalConversion: { original: '', converted: '' },
  explode: { delimiter: ',' },
  loadDataObject: { loadStrategy: 'id' },
  loadAsset: { loadStrategy: 'id' }
}

// map values takes the counterpart at the same position, so both lists need as many values
export const mapListsDiffer = ({ type, settings }: TransformationStep): boolean =>
  type === 'conditionalConversion' &&
  (settings.original ?? '').split('|').length !== (settings.converted ?? '').split('|').length

const TRIM_MODES = ['both', 'left', 'right']
const LOAD_STRATEGIES = ['id', 'path']

export interface TransformationSettingsProps {
  step: TransformationStep
  // tells the fields of one step from those of another
  id: string
  onChange: (settings: Record<string, string>) => void
}

export const TransformationSettings = ({ step, id, onChange }: TransformationSettingsProps): React.JSX.Element | null => {
  const { t } = useTranslation()
  const { type, settings } = step

  const set = (name: string, value: string): void => { onChange({ ...settings, [name]: value }) }

  const text = (name: string, label: string, extra?: string): React.JSX.Element => (
    <Form.Item
      extra={ extra }
      htmlFor={ `${id}-${name}` }
      label={ label }
    >
      <Input
        id={ `${id}-${name}` }
        onChange={ (event) => { set(name, event.target.value) } }
        style={ { width: TEXT_WIDTH } }
        value={ settings[name] ?? '' }
      />
    </Form.Item>
  )

  const prefix = 'data-importer.ad-hoc-import.transformations'

  switch (type) {
    case 'trim':
      return (
        <Form.Item
          htmlFor={ `${id}-mode` }
          label={ t(`${prefix}.trim.mode`) }
        >
          <Select
            aria-label={ t(`${prefix}.trim.mode`) }
            id={ `${id}-mode` }
            onChange={ (value: string) => { set('mode', value) } }
            options={ TRIM_MODES.map((mode) => ({ value: mode, label: t(`${prefix}.trim.mode.${mode}`) })) }
            style={ { width: TEXT_WIDTH } }
            value={ settings.mode ?? 'both' }
          />
        </Form.Item>
      )
    case 'stringReplace':
      return (
        <Flex gap="small">
          { text('search', t(`${prefix}.replace.search`)) }
          { text('replace', t(`${prefix}.replace.replace`)) }
        </Flex>
      )
    case 'conditionalConversion':
      return (
        <Flex
          gap="small"
          vertical
        >
          <Flex gap="small">
            { text('original', t(`${prefix}.map.original`)) }
            { text('converted', t(`${prefix}.map.converted`)) }
          </Flex>
          <Text type="secondary">{ t(`${prefix}.map.hint`) }</Text>
        </Flex>
      )
    case 'explode':
      return text('delimiter', t(`${prefix}.split.delimiter`))
    case 'loadDataObject':
    case 'loadAsset':
      return (
        <Form.Item label={ t(`${prefix}.load.strategy`) }>
          <Segmented
            onChange={ (value) => { set('loadStrategy', String(value)) } }
            options={ LOAD_STRATEGIES.map((strategy) => ({ value: strategy, label: t(`${prefix}.load.strategy.${strategy}`) })) }
            value={ settings.loadStrategy ?? 'id' }
          />
        </Form.Item>
      )
    default:
      return null
  }
}
