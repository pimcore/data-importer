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
import { Button, Flex, ModalFooter, Select } from '@pimcore/studio-ui-bundle/components'
import { failedRowsUrl, isDone, type ImportRun } from './import-step/import-state'
import { type ErrorMode } from './preview-step/preview-state'

const ERROR_MODE_WIDTH = 260
const ERROR_MODE_ID = 'ad-hoc-import-error-mode'
const ERROR_MODES: ErrorMode[] = ['skip', 'values']

interface PreviewFooterProps {
  errorMode: ErrorMode
  importCount: number
  onBack: () => void
  onErrorModeChange: (mode: ErrorMode) => void
  onImport: () => void
}

export const PreviewFooter = ({ errorMode, importCount, onBack, onErrorModeChange, onImport }: PreviewFooterProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <ModalFooter
      divider
      justify="space-between"
    >
      <Flex
        align="center"
        gap="small"
      >
        <label htmlFor={ ERROR_MODE_ID }>{ t('data-importer.ad-hoc-import.preview.rows-with-errors') }</label>
        <Select
          id={ ERROR_MODE_ID }
          onChange={ (value: ErrorMode) => { onErrorModeChange(value) } }
          options={ ERROR_MODES.map((mode) => ({
            value: mode,
            label: t(`data-importer.ad-hoc-import.preview.error-mode.${mode}`)
          })) }
          value={ errorMode }
          width={ ERROR_MODE_WIDTH }
        />
      </Flex>
      <Flex gap="small">
        <Button onClick={ onBack }>{ t('data-importer.ad-hoc-import.back') }</Button>
        <Button
          disabled={ importCount === 0 }
          onClick={ onImport }
          type="primary"
        >
          { t('data-importer.ad-hoc-import.preview.import', { count: importCount }) }
        </Button>
      </Flex>
    </ModalFooter>
  )
}

interface ImportFooterProps {
  onClose: () => void
  run: ImportRun | undefined
  runFileId: string | null
}

export const ImportFooter = ({ onClose, run, runFileId }: ImportFooterProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <ModalFooter
      divider
      justify={ isDone(run) && run.failed > 0 ? 'space-between' : 'flex-end' }
    >
      { isDone(run) && run.failed > 0 && runFileId !== null && (
        <Button href={ failedRowsUrl(runFileId) }>
          { t('data-importer.ad-hoc-import.result.download-failed', { count: run.failed }) }
        </Button>
      ) }
      <Button
        onClick={ onClose }
        type={ isDone(run) ? 'primary' : undefined }
      >
        { t(isDone(run) ? 'data-importer.ad-hoc-import.result.close' : 'data-importer.ad-hoc-import.run.close-keep-running') }
      </Button>
    </ModalFooter>
  )
}

interface StepFooterProps {
  canGoOn: boolean
  isFirstStep: boolean
  onBack: () => void
  onClose: () => void
  onNext: () => void
}

export const StepFooter = ({ canGoOn, isFirstStep, onBack, onClose, onNext }: StepFooterProps): React.JSX.Element => {
  const { t } = useTranslation()

  return (
    <ModalFooter divider>
      { isFirstStep
        ? <Button onClick={ onClose }>{ t('data-importer.ad-hoc-import.cancel') }</Button>
        : <Button onClick={ onBack }>{ t('data-importer.ad-hoc-import.back') }</Button> }
      <Button
        disabled={ !canGoOn }
        onClick={ onNext }
        type="primary"
      >
        { t(isFirstStep ? 'data-importer.ad-hoc-import.next' : 'data-importer.ad-hoc-import.next-preview') }
      </Button>
    </ModalFooter>
  )
}
