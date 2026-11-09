/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useRef, useState } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Button, Flex, IconButton, Text } from '@pimcore/studio-ui-bundle/components'
import { ApiError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import { useBundleDataImporterAdHocImportPreviewTransformationsMutation } from '../../data-importer-api-slice-enhanced'
import {
  type BundleDataImporterAdHocImportFileSettings,
  type BundleDataImporterAdHocImportTransformationCell,
  type BundleDataImporterAdHocImportTransformationResponse
} from '../../data-importer-api-slice.gen'
import { settingsOf } from '../file-step/file-settings'
import { type FilePhase } from '../file-step/file-step-state'
import { type MappingField, type MappingRow, type TransformationStep } from './mapping-state'
import { DEFAULT_SETTINGS, mapListsDiffer, TransformationSettings } from './transformation-settings'

// a pause in typing before the values are run again
const PREVIEW_DELAY = 300

const PREFIX = 'data-importer.ad-hoc-import.transformations'

// what the transformations run on: the stored file under its settings, and the class imported into
export interface TransformationSource {
  fileId: string
  classId: string
  settings: BundleDataImporterAdHocImportFileSettings
}

// a column's transformations run on the stored file as it is read now
export const transformationSourceOf = (phase: FilePhase, classId: string | undefined): TransformationSource | null => {
  if (phase.kind !== 'inspected' || phase.inspected.fileId === undefined || phase.inspected.fileId === null ||
    classId === undefined) {
    return null
  }

  return { fileId: phase.inspected.fileId, classId, settings: settingsOf(phase.inspected) }
}

export type TransformationAnswer = BundleDataImporterAdHocImportTransformationResponse
export type TransformationCell = BundleDataImporterAdHocImportTransformationCell

// what the server is asked for one column's steps
export const transformationRequest = (
  source: TransformationSource,
  column: string,
  field: string,
  steps: TransformationStep[]
): Parameters<ReturnType<typeof useBundleDataImporterAdHocImportPreviewTransformationsMutation>[0]>[0] => ({
  fileId: source.fileId,
  bundleDataImporterAdHocImportTransformationParameters: {
    ...source.settings,
    classId: source.classId,
    column,
    field,
    transformationPipeline: steps
  }
})

// the row whose value is shown: the first that holds one, as the column's sample is
export const firstValueRow = (answer: TransformationAnswer | null): number =>
  Math.max(0, answer?.values.findIndex(({ items }) => items.length > 0) ?? 0)

export interface TransformationPreview {
  answer: TransformationAnswer | null
  isLoading: boolean
}

// the server runs the column's first values through its steps, each time they change and typing has paused
export const useTransformationPreview = (
  { row, field, source, steps }: { row: MappingRow, field: MappingField | undefined, source: TransformationSource | null, steps: TransformationStep[] }
): TransformationPreview => {
  const [answer, setAnswer] = useState<TransformationAnswer | null>(null)
  const [isLoading, setIsLoading] = useState(field !== undefined && source !== null)
  const [preview] = useBundleDataImporterAdHocImportPreviewTransformationsMutation()
  // only the answer to the steps asked for last counts
  const latest = useRef(0)
  const settingsKey = JSON.stringify(source?.settings)

  useEffect(() => {
    if (field === undefined || source === null) {
      latest.current += 1
      setAnswer(null)
      setIsLoading(false)
      return
    }

    const request = ++latest.current
    setIsLoading(true)

    const timer = setTimeout(() => {
      void preview(transformationRequest(source, row.column, field.key, steps)).then((result) => {
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
  }, [steps, source?.fileId, source?.classId, settingsKey, row.column, field?.key, preview])

  return { answer, isLoading }
}

export interface TransformationsEditorProps {
  field: MappingField
  steps: TransformationStep[]
  preview: TransformationPreview
  onChange: (steps: TransformationStep[]) => void
}

export const TransformationsEditor = ({ field, steps, preview: { answer, isLoading }, onChange }: TransformationsEditorProps): React.JSX.Element => {
  const { t } = useTranslation()
  const label = (type: string): string => t(`${PREFIX}.type.${type}`)

  const add = (type: string): void => {
    onChange([...steps, { type, settings: { ...DEFAULT_SETTINGS[type] } }])
  }

  const change = (position: number, settings: Record<string, string>): void => {
    onChange(steps.map((step, index) => index === position ? { ...step, settings } : step))
  }

  const remove = (position: number): void => {
    onChange(steps.filter((_, index) => index !== position))
  }

  const offered = answer?.offered ?? []
  // an answer that came for other steps tells nothing about these
  const settled = !isLoading && answer !== null

  return (
    <Flex
      gap="small"
      vertical
    >
      <Text type="secondary">{ t(`${PREFIX}.intro`) }</Text>

      <Flex
        aria-label={ t('data-importer.ad-hoc-import.mapping.transformations') }
        gap="small"
        role="list"
        vertical
      >
        { steps.map((step, position) => {
          const error = answer?.steps[position]?.error
          const value = answer?.steps[position]?.values[firstValueRow(answer)]

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
                { value !== undefined && <Text type="secondary">{ `→ ${cellText(t, value)}` }</Text> }
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
                  message={ t(mapListsDiffer(step) ? `${PREFIX}.map.unequal` : `${PREFIX}.step-error`) }
                  showIcon
                  type="error"
                />
              ) }
            </Flex>
          )
        }) }
      </Flex>

      <Text strong>{ t(`${PREFIX}.add`) }</Text>
      <Flex
        gap="small"
        wrap="wrap"
      >
        { offered.map((type) => (
          <Button
            disabled={ !settled }
            key={ type }
            onClick={ () => { add(type) } }
          >
            { `+ ${label(type)}` }
          </Button>
        )) }
      </Flex>

      { answer !== null && !answer.fits && (
        <Alert
          message={ t(`${PREFIX}.does-not-fit`, { field: field.title, interpolation: { escapeValue: false } }) }
          showIcon
          type="warning"
        />
      ) }
      { answer?.conversion !== null && answer?.conversion !== undefined && (
        <Text type="secondary">{ t(`${PREFIX}.conversion.${answer.conversion}`) }</Text>
      ) }
    </Flex>
  )
}

type Translate = ReturnType<typeof useTranslation>['t']

// a cell's value as a person reads it
export const cellText = (t: Translate, cell: TransformationCell | undefined): string => {
  if (cell === undefined) {
    return ''
  }
  if (cell.failed) {
    return t(`${PREFIX}.failed`)
  }

  return cell.items.length === 0 ? t(`${PREFIX}.empty`) : cell.items.join(', ')
}
