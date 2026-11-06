/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo, useState } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Flex, Form, Input, Select, Switch } from '@pimcore/studio-ui-bundle/components'
import { type BundleDataImporterAdHocImportFileSettings } from '../../data-importer-api-slice.gen'
import { type InspectedFile } from './file-step-state'

const DELIMITERS = [
  { value: ';', label: 'data-importer.ad-hoc-import.settings.delimiter.semicolon' },
  { value: ',', label: 'data-importer.ad-hoc-import.settings.delimiter.comma' },
  { value: '\t', label: 'data-importer.ad-hoc-import.settings.delimiter.tab' },
  { value: '|', label: 'data-importer.ad-hoc-import.settings.delimiter.pipe' }
]

const SELECT_WIDTH = 200
const CHARACTER_WIDTH = 64
const FORM_ITEM_STYLE = { marginBottom: 0 }

export const settingsOf = (inspected: InspectedFile): BundleDataImporterAdHocImportFileSettings => ({
  delimiter: inspected.delimiter,
  enclosure: inspected.enclosure,
  escape: inspected.escape,
  hasHeaderRow: inspected.hasHeaderRow,
  sheetName: inspected.sheetName
})

interface CharacterInputProps {
  id: string
  value: string
  // the escape may be empty, the enclosure not
  allowEmpty: boolean
  onCommit: (value: string) => void
}

const CharacterInput = ({ id, value, allowEmpty, onCommit }: CharacterInputProps): React.JSX.Element => {
  const [draft, setDraft] = useState(value)

  return (
    <Input
      id={ id }
      maxLength={ 1 }
      onChange={ (event) => {
        const next = event.target.value
        setDraft(next)
        if (next !== '' || allowEmpty) {
          onCommit(next)
        }
      } }
      style={ { width: CHARACTER_WIDTH } }
      value={ draft }
    />
  )
}

export interface FileSettingsProps {
  inspected: InspectedFile
  onChange: (settings: BundleDataImporterAdHocImportFileSettings) => void
}

export const FileSettings = ({ inspected, onChange }: FileSettingsProps): React.JSX.Element => {
  const { t } = useTranslation()

  const change = (changed: Partial<BundleDataImporterAdHocImportFileSettings>): void => {
    onChange({ ...settingsOf(inspected), ...changed })
  }

  const delimiterOptions = useMemo(() => DELIMITERS.map(({ value, label }) => ({
    value,
    label: value === inspected.detectedDelimiter
      ? t('data-importer.ad-hoc-import.settings.delimiter.detected', { delimiter: t(label), interpolation: { escapeValue: false } })
      : t(label)
  })), [inspected.detectedDelimiter, t])

  const columnNames = (
    <Form.Item
      label={ t('data-importer.ad-hoc-import.settings.column-names') }
      style={ FORM_ITEM_STYLE }
    >
      <Switch
        checked={ inspected.hasHeaderRow }
        labelRight={ t('data-importer.ad-hoc-import.settings.in-first-row') }
        onChange={ (hasHeaderRow: boolean) => { change({ hasHeaderRow }) } }
        size="small"
      />
    </Form.Item>
  )

  if (inspected.type === 'xlsx') {
    return (
      <Flex gap="normal">
        <Form.Item
          htmlFor="ad-hoc-import-sheet"
          label={ t('data-importer.ad-hoc-import.settings.sheet') }
          style={ FORM_ITEM_STYLE }
        >
          <Select
            id="ad-hoc-import-sheet"
            onChange={ (sheetName: string) => { change({ sheetName }) } }
            options={ inspected.sheets.map((sheet) => ({ value: sheet, label: sheet })) }
            style={ { width: SELECT_WIDTH } }
            value={ inspected.sheetName }
          />
        </Form.Item>
        { columnNames }
      </Flex>
    )
  }

  return (
    <Flex gap="normal">
      <Form.Item
        htmlFor="ad-hoc-import-delimiter"
        label={ t('data-importer.ad-hoc-import.settings.delimiter') }
        style={ FORM_ITEM_STYLE }
      >
        <Select
          id="ad-hoc-import-delimiter"
          onChange={ (delimiter: string) => { change({ delimiter }) } }
          options={ delimiterOptions }
          style={ { width: SELECT_WIDTH } }
          value={ inspected.delimiter }
        />
      </Form.Item>
      <Form.Item
        htmlFor="ad-hoc-import-enclosure"
        label={ t('data-importer.ad-hoc-import.settings.enclosure') }
        style={ FORM_ITEM_STYLE }
      >
        <CharacterInput
          allowEmpty={ false }
          id="ad-hoc-import-enclosure"
          onCommit={ (enclosure) => { change({ enclosure }) } }
          value={ inspected.enclosure ?? '' }
        />
      </Form.Item>
      <Form.Item
        htmlFor="ad-hoc-import-escape"
        label={ t('data-importer.ad-hoc-import.settings.escape') }
        style={ FORM_ITEM_STYLE }
      >
        <CharacterInput
          allowEmpty
          id="ad-hoc-import-escape"
          onCommit={ (escape) => { change({ escape }) } }
          value={ inspected.escape ?? '' }
        />
      </Form.Item>
      { columnNames }
    </Flex>
  )
}
