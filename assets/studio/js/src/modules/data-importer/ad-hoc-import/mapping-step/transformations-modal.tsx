/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useMemo, useRef, useState } from 'react'
import { type ColumnDef } from '@tanstack/react-table'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Button, Flex, Grid, IconButton, Modal, ModalFooter, Select, Skeleton, Text } from '@pimcore/studio-ui-bundle/components'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { useBundleDataImporterAdHocImportPreviewTransformationsMutation } from '../../data-importer-api-slice-enhanced'
import {
  type BundleDataImporterAdHocImportFileSettings,
  type BundleDataImporterAdHocImportTransformationCell,
  type BundleDataImporterAdHocImportTransformationResponse
} from '../../data-importer-api-slice.gen'
import { type MappingField, type MappingRow, type TransformationStep } from './mapping-state'
import { DEFAULT_SETTINGS, TransformationSettings } from './transformation-settings'

const MODAL_WIDTH = 960
// a pause in typing before the values are run again
const PREVIEW_DELAY = 300

const PREFIX = 'data-importer.ad-hoc-import.transformations'

// what the transformations run on: the stored file under its settings, and the class imported into
export interface TransformationSource {
  fileId: string
  classId: string
  settings: BundleDataImporterAdHocImportFileSettings
}

type Answer = BundleDataImporterAdHocImportTransformationResponse
type Cell = BundleDataImporterAdHocImportTransformationCell

interface ChainRow {
  values: Cell
  steps: Array<Cell | undefined>
  result: Cell | undefined
}

export interface TransformationsModalProps {
  row: MappingRow
  field: MappingField
  source: TransformationSource
  onApply: (transformations: TransformationStep[], fits: boolean) => void
  onCancel: () => void
}

export const TransformationsModal = ({ row, field, source, onApply, onCancel }: TransformationsModalProps): React.JSX.Element => {
  const { t } = useTranslation()
  const [steps, setSteps] = useState<TransformationStep[]>(row.transformations)
  const [answer, setAnswer] = useState<Answer | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [preview] = useBundleDataImporterAdHocImportPreviewTransformationsMutation()
  // only the answer to the steps asked for last counts
  const latest = useRef(0)
  const { fileId, classId, settings } = source
  const settingsKey = JSON.stringify(settings)

  useEffect(() => {
    const request = ++latest.current
    setIsLoading(true)

    const timer = setTimeout(() => {
      void preview({
        fileId,
        bundleDataImporterAdHocImportTransformationParameters: {
          ...settings,
          classId,
          column: row.column,
          field: field.key,
          transformationPipeline: steps
        }
      }).then((result) => {
        if (request !== latest.current) {
          return
        }
        setIsLoading(false)

        if ('error' in result && result.error !== undefined) {
          trackError(new ApiError(result.error))
          setAnswer(null)
          return
        }
        setAnswer(result.data)
      })
    }, PREVIEW_DELAY)

    return () => { clearTimeout(timer) }
    // the settings by their values: another object with the same ones asks nothing new
  }, [steps, fileId, classId, settingsKey, row.column, field.key, preview])

  const label = (type: string): string => t(`${PREFIX}.type.${type}`)
  const fieldTitle = field.title

  const cellText = (cell: Cell | undefined): string => {
    if (cell === undefined) {
      return ''
    }
    if (cell.failed) {
      return t(`${PREFIX}.failed`)
    }

    return cell.items.length === 0 ? t(`${PREFIX}.empty`) : cell.items.join(', ')
  }

  const chain = useMemo((): ChainRow[] => answer === null
    ? []
    : answer.values.map((values, position) => ({
        values,
        steps: answer.steps.map((step) => step.values[position]),
        result: answer.results[position]
      })), [answer])

  const columns: Array<ColumnDef<ChainRow>> = [
    { id: 'in-file', header: t(`${PREFIX}.in-file`), accessorFn: (chainRow) => cellText(chainRow.values) },
    ...steps.map((step, position): ColumnDef<ChainRow> => ({
      id: `step-${position}`,
      header: `${position + 1}. ${label(step.type)}`,
      accessorFn: (chainRow) => cellText(chainRow.steps[position])
    })),
    {
      id: 'into-field',
      header: t(`${PREFIX}.into-field`, { field: fieldTitle, interpolation: { escapeValue: false } }),
      accessorFn: (chainRow) => cellText(chainRow.result)
    }
  ]

  const add = (type: string): void => {
    setSteps([...steps, { type, settings: { ...DEFAULT_SETTINGS[type] } }])
  }

  const change = (position: number, settings: Record<string, string>): void => {
    setSteps(steps.map((step, index) => index === position ? { ...step, settings } : step))
  }

  const remove = (position: number): void => {
    setSteps(steps.filter((_, index) => index !== position))
  }

  const offered = answer?.offered ?? []
  // an answer that came for other steps tells nothing about these
  const settled = !isLoading && answer !== null

  return (
    <Modal
      footer={ (
        <ModalFooter>
          <Button onClick={ onCancel }>{ t(`${PREFIX}.cancel`) }</Button>
          <Button
            disabled={ !settled }
            onClick={ () => { if (answer !== null) { onApply(steps, answer.fits) } } }
            type="primary"
          >
            { t(`${PREFIX}.apply`) }
          </Button>
        </ModalFooter>
      ) }
      onCancel={ onCancel }
      open
      title={ t(`${PREFIX}.title`, { column: row.label, field: fieldTitle, interpolation: { escapeValue: false } }) }
      width={ MODAL_WIDTH }
    >
      <Flex
        gap="normal"
        vertical
      >
        { steps.length === 0 && <Text type="secondary">{ t(`${PREFIX}.none`) }</Text> }

        <Flex
          aria-label={ t('data-importer.ad-hoc-import.mapping.transformations') }
          gap="small"
          role="list"
          vertical
        >
          { steps.map((step, position) => {
            const error = answer?.steps[position]?.error

            return (
              <Flex
                gap="small"
                key={ `${position}-${step.type}` }
                role="listitem"
                vertical
              >
                <Flex
                  align="center"
                  gap="small"
                >
                  <Text strong>{ `${position + 1}. ${label(step.type)}` }</Text>
                  <IconButton
                    aria-label={ t(`${PREFIX}.remove`, { step: label(step.type) }) }
                    icon={ { value: 'trash' } }
                    onClick={ () => { remove(position) } }
                    type="link"
                  />
                </Flex>
                <TransformationSettings
                  id={ `transformation-${position}` }
                  onChange={ (settings) => { change(position, settings) } }
                  step={ step }
                />
                { error !== null && error !== undefined && (
                  <Alert
                    message={ t(`${PREFIX}.step-error`) }
                    showIcon
                    type="error"
                  />
                ) }
              </Flex>
            )
          }) }
        </Flex>

        <Select
          aria-label={ t(`${PREFIX}.add`) }
          disabled={ offered.length === 0 }
          onChange={ (type: string) => { if (type !== '') { add(type) } } }
          options={ offered.map((type) => ({ value: type, label: label(type) })) }
          placeholder={ t(`${PREFIX}.add`) }
          style={ { width: 240 } }
          value={ null }
        />

        { answer !== null && !answer.fits && (
          <Alert
            message={ t(`${PREFIX}.does-not-fit`, { field: fieldTitle, interpolation: { escapeValue: false } }) }
            showIcon
            type="warning"
          />
        ) }
        { answer?.conversion !== null && answer?.conversion !== undefined && (
          <Text type="secondary">{ t(`${PREFIX}.conversion.${answer.conversion}`) }</Text>
        ) }

        <Text strong>{ t(`${PREFIX}.values`) }</Text>
        { answer === null && isLoading
          ? <Skeleton active />
          : (
            <Grid
              autoWidth
              columns={ columns }
              data={ chain }
              isLoading={ isLoading }
            />
            ) }
      </Flex>
    </Modal>
  )
}
