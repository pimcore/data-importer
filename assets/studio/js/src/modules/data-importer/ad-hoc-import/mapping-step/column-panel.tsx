/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useRef } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Button, Flex, IconButton, Tabs, Text } from '@pimcore/studio-ui-bundle/components'
import { useStyles } from './column-panel.styles'
import { FieldPanel } from './field-panel'
import { type Mapping, type MappingField, type MappingRow, type TransformationStep } from './mapping-state'
import {
  cellText,
  firstValueRow,
  type TransformationPreview,
  TransformationsEditor,
  type TransformationSource,
  useTransformationPreview
} from './transformations-editor'

const PANEL = 'data-importer.ad-hoc-import.mapping.panel'
const DIALOG = '[role="dialog"]'

export type ColumnPanelTab = 'field' | 'transformations'

interface ValueChainProps {
  row: MappingRow
  field: MappingField | undefined
  preview: TransformationPreview
}

// the way of the column's first value: as it is in the file, after each step, in the field
const ValueChain = ({ row, field, preview: { answer } }: ValueChainProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const valueRow = firstValueRow(answer)
  const result = answer?.results[valueRow]
  const steps = answer?.steps ?? []
  const fieldName = row.choice?.language === null || row.choice?.language === undefined
    ? field?.title
    : `${field?.title} (${row.choice.language.toUpperCase()})`

  return (
    <div className={ styles.chain }>
      <Text type="secondary">{ t(`${PANEL}.first-value`, { value: row.sample ?? '', interpolation: { escapeValue: false } }) }</Text>
      { /* a step of the answer has no identity but its place in the chain */ }
      { React.Children.toArray(steps.map((step) => (
        <React.Fragment>
          <span aria-hidden>→</span>
          <span className={ styles.chip }>{ cellText(t, step.values[valueRow]) }</span>
        </React.Fragment>
      ))) }
      <span aria-hidden>→</span>
      { field === undefined
        ? <span className={ styles.chipOpen }>{ t(`${PANEL}.choose-field`) }</span>
        : (
          <span className={ result?.failed === true || answer?.fits === false ? styles.chipFailed : styles.chipResult }>
            { result === undefined ? fieldName : `${fieldName}: ${cellText(t, result)}` }
          </span>
          ) }
    </div>
  )
}

export interface ColumnPanelProps {
  row: MappingRow
  mapping: Mapping
  fields: MappingField[]
  languages: string[]
  tab: ColumnPanelTab
  // what the transformations of a column run on; none, no transformations
  source: TransformationSource | null
  onTabChange: (tab: ColumnPanelTab) => void
  onPick: (row: MappingRow, field: MappingField) => void
  onSteps: (steps: TransformationStep[]) => void
  onFits: (steps: TransformationStep[], fits: boolean) => void
  onRemoveField: () => void
  onSkip: () => void
  onPrevious: () => void
  onNext: () => void
  onClose: () => void
}

export const ColumnPanel = ({
  row,
  mapping,
  fields,
  languages,
  tab,
  source,
  onTabChange,
  onPick,
  onSteps,
  onFits,
  onRemoveField,
  onSkip,
  onPrevious,
  onNext,
  onClose
}: ColumnPanelProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const field = fields.find(({ key }) => key === row.choice?.field)
  // a classification store key has no transformations: the server knows no type for a key here
  const onStoreKey = row.choice?.key !== undefined
  const transformable = field !== undefined && !onStoreKey && source !== null
  const steps = row.transformations
  const preview = useTransformationPreview({ row, field: transformable ? field : undefined, source, steps })

  // whether the column's values reach their field, from the server's answer to the steps as they stand now
  useEffect(() => {
    if (!preview.isLoading && preview.answer !== null && row.fits !== preview.answer.fits) {
      onFits(steps, preview.answer.fits)
    }
  }, [preview.answer, preview.isLoading])

  const panelElement = useRef<HTMLElement>(null)

  // the focus goes with the panel; moved on to the next column, the button that had it is gone
  useEffect(() => {
    panelElement.current?.focus({ preventScroll: true })
  }, [])

  // Escape leaves the panel, not the whole dialog, wherever in that dialog the focus is; a dialog over the panel has its own
  useEffect(() => {
    const leave = (event: KeyboardEvent): void => {
      const target = event.target as Element
      if (event.key === 'Escape' && (target === document.body || target.closest(DIALOG) === panelElement.current?.closest(DIALOG))) {
        event.stopPropagation()
        onClose()
      }
    }

    window.addEventListener('keydown', leave, true)

    return () => { window.removeEventListener('keydown', leave, true) }
  }, [onClose])

  const stepsLabel = steps.length === 0
    ? t(`${PANEL}.tab.transformations`)
    : t(`${PANEL}.tab.transformations-count`, { count: steps.length })

  return (
    <>
      <button
        aria-label={ t(`${PANEL}.close`) }
        className={ styles.backdrop }
        onClick={ onClose }
        tabIndex={ -1 }
        type="button"
      />
      <aside
        aria-label={ t(`${PANEL}.aria`, { column: row.label, interpolation: { escapeValue: false } }) }
        className={ styles.panel }
        ref={ panelElement }
        tabIndex={ -1 }
      >
        <div className={ styles.head }>
          <Flex
            align="center"
            gap="small"
          >
            <Flex
              className={ styles.grow }
              vertical
            >
              <span className={ styles.caption }>{ t(`${PANEL}.column`) }</span>
              <span className={ styles.name }>{ row.label }</span>
            </Flex>
            <IconButton
              aria-label={ t(`${PANEL}.previous`) }
              icon={ { value: 'chevron-left' } }
              onClick={ onPrevious }
            />
            <IconButton
              aria-label={ t(`${PANEL}.next`) }
              icon={ { value: 'chevron-right' } }
              onClick={ onNext }
            />
            <IconButton
              aria-label={ t(`${PANEL}.close`) }
              icon={ { value: 'close' } }
              onClick={ onClose }
              type="text"
            />
          </Flex>
          <ValueChain
            field={ field }
            preview={ preview }
            row={ row }
          />
        </div>
        <Tabs
          activeKey={ tab }
          className={ styles.tabs }
          items={ [
            {
              key: 'field',
              label: t(`${PANEL}.tab.field`),
              children: (
                <FieldPanel
                  fields={ fields }
                  languages={ languages }
                  mapping={ mapping }
                  onPick={ onPick }
                  row={ row }
                />
              )
            },
            {
              key: 'transformations',
              label: stepsLabel,
              disabled: source === null,
              children: transformable
                ? (
                  <TransformationsEditor
                    field={ field }
                    onChange={ onSteps }
                    preview={ preview }
                    steps={ steps }
                  />
                  )
                : <Text type={ onStoreKey ? 'secondary' : 'warning' }>{ t(`${PANEL}.${onStoreKey ? 'store-key-no-transformations' : 'field-first'}`) }</Text>
            }
          ] }
          onChange={ (next: string) => { onTabChange(next as ColumnPanelTab) } }
        />
        <div className={ styles.actions }>
          { row.choice !== undefined && <Button onClick={ onRemoveField }>{ t(`${PANEL}.remove-field`) }</Button> }
          <Button onClick={ onSkip }>{ t('data-importer.ad-hoc-import.mapping.dont-import') }</Button>
          <span className={ styles.grow } />
          <Button
            onClick={ onNext }
            type="primary"
          >
            { t(`${PANEL}.next-column`) }
          </Button>
        </div>
      </aside>
    </>
  )
}
