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
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Button, Modal, ModalFooter, Steps } from '@pimcore/studio-ui-bundle/components'
import { ApiError, GeneralError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import {
  useBundleDataImporterAdHocImportDeleteFileMutation,
  useBundleDataImporterAdHocImportInspectFileMutation,
  useBundleDataImporterAdHocImportLoadMappingMutation
} from '../data-importer-api-slice-enhanced'
import { type BundleDataImporterAdHocImportFileSettings } from '../data-importer-api-slice.gen'
import { type AdHocImportTarget } from './dialog-store'
import { settingsOf } from './file-step/file-settings'
import { FileStep } from './file-step/file-step'
import {
  canContinue,
  hasAcceptedExtension,
  initialObjects,
  keepMatchColumn,
  refusedForFormat,
  type FilePhase,
  type ObjectsSettings
} from './file-step/file-step-state'
import { UploadAborted, UploadFailed, uploadFile, type RunningUpload } from './file-step/upload-file'
import { canContinueMapping, createMapping, mappingBasis, withSamples, type Mapping, type MappingField } from './mapping-step/mapping-state'
import { withLanguages } from './mapping-step/mapping-choices'
import { MappingStep } from './mapping-step/mapping-step'
import { type TransformationSource } from './mapping-step/transformations-modal'
import { useLanguages } from './mapping-step/use-languages'

const DIALOG_WIDTH = 1160
const STEPS_WIDTH = 720

const STEPS = ['file', 'mapping', 'preview', 'import']

const titlePath = ({ folderPath, folderLabel }: AdHocImportTarget): string => {
  const path = folderPath.split('/').filter((part) => part !== '').join(' / ')

  return path === '' ? folderLabel : path
}

export interface AdHocImportDialogProps {
  target: AdHocImportTarget
  onClose: () => void
}

export const AdHocImportDialog = ({ target, onClose }: AdHocImportDialogProps): React.JSX.Element => {
  const { t } = useTranslation()
  const languages = useLanguages()
  const [phase, setPhase] = useState<FilePhase>({ kind: 'empty' })
  const [objects, setObjects] = useState<ObjectsSettings>(initialObjects)
  const [step, setStep] = useState(0)
  const [isInspecting, setIsInspecting] = useState(false)
  const [mapping, setMapping] = useState<Mapping | null>(null)
  const [fields, setFields] = useState<MappingField[]>([])
  const [isLoadingMapping, setIsLoadingMapping] = useState(false)

  const [inspectFile] = useBundleDataImporterAdHocImportInspectFileMutation()
  const [deleteFile] = useBundleDataImporterAdHocImportDeleteFileMutation()
  const [loadMapping] = useBundleDataImporterAdHocImportLoadMappingMutation()

  const upload = useRef<RunningUpload | null>(null)
  // only the answer to the latest settings counts
  const latestInspection = useRef(0)
  // a change made while an answer is pending builds on what was asked for, not on the last answer
  const requestedSettings = useRef<BundleDataImporterAdHocImportFileSettings | null>(null)
  // only the mapping asked for last counts
  const latestMapping = useRef(0)

  const deleteStoredFile = (): void => {
    if (phase.kind === 'inspected' && phase.inspected.fileId !== undefined && phase.inspected.fileId !== null) {
      void deleteFile({ fileId: phase.inspected.fileId }).then((result) => {
        if ('error' in result && result.error !== undefined) {
          trackError(new ApiError(result.error))
        }
      })
    }
  }

  const reset = (): void => {
    upload.current?.abort()
    upload.current = null
    latestInspection.current += 1
    requestedSettings.current = null
    setIsInspecting(false)
    setPhase({ kind: 'empty' })
  }

  const onFile = (file: File): void => {
    const info = { name: file.name, size: file.size }

    // another file's column at the same position would be another column
    setObjects((current) => ({ ...current, matchColumn: undefined }))

    if (!hasAcceptedExtension(file.name)) {
      setPhase({ kind: 'inspected', file: info, inspected: refusedForFormat() })
      return
    }

    setPhase({ kind: 'uploading', file: info, loaded: 0, total: file.size })

    const running = uploadFile(file, target.folderId, (loaded, total) => {
      setPhase((current) => current.kind === 'uploading' ? { ...current, loaded, total } : current)
    })
    upload.current = running

    running.result.then((inspected) => {
      if (upload.current !== running) {
        return
      }
      upload.current = null
      setPhase({ kind: 'inspected', file: info, inspected })
    }).catch((error: unknown) => {
      if (error instanceof UploadAborted) {
        return
      }
      if (upload.current === running) {
        upload.current = null
        setPhase({ kind: 'empty' })
      }
      trackError(error instanceof UploadFailed && error.data !== null
        ? new ApiError({ status: 500, data: error.data })
        : new GeneralError('The file could not be uploaded'))
    })
  }

  const onSettingsChange = (changed: Partial<BundleDataImporterAdHocImportFileSettings>): void => {
    if (phase.kind !== 'inspected' || phase.inspected.fileId === undefined || phase.inspected.fileId === null) {
      return
    }

    const before = phase.inspected
    const settings = { ...(requestedSettings.current ?? settingsOf(before)), ...changed }
    requestedSettings.current = settings

    const inspection = ++latestInspection.current
    setIsInspecting(true)

    void inspectFile({ fileId: phase.inspected.fileId, bundleDataImporterAdHocImportFileSettings: settings }).then((result) => {
      if (inspection !== latestInspection.current) {
        return
      }
      requestedSettings.current = null
      setIsInspecting(false)

      if ('error' in result && result.error !== undefined) {
        trackError(new ApiError(result.error))
        if ('status' in result.error && result.error.status === 404) {
          // the file is gone: start over
          setPhase({ kind: 'empty' })
          return
        }
        // the file may well be there: it stays, with the settings last read, and a new object brings back what is typed
        setPhase((current) => current.kind === 'inspected' ? { ...current, inspected: { ...current.inspected } } : current)
        return
      }

      const inspected = result.data
      setObjects((current) => keepMatchColumn(current, before, inspected))
      setPhase((current) => current.kind === 'inspected' ? { ...current, inspected } : current)
    })
  }

  // a mapping stays while the file, the class and what to match by stay
  const toMapping = (): void => {
    setStep(1)

    if (phase.kind !== 'inspected' || objects.classId === undefined) {
      return
    }
    const { inspected } = phase
    if (mapping?.basis === mappingBasis(inspected, objects)) {
      setMapping(withSamples(mapping, inspected))
      return
    }

    const request = ++latestMapping.current
    setMapping(null)
    setIsLoadingMapping(true)

    void loadMapping({
      bundleDataImporterMappingSuggestionParameters: {
        classId: objects.classId,
        columns: inspected.columns.map(({ dataIndex, label }) => ({ index: dataIndex, label }))
      }
    }).then((result) => {
      if (request !== latestMapping.current) {
        return
      }
      setIsLoadingMapping(false)

      if ('error' in result && result.error !== undefined) {
        trackError(new ApiError(result.error))
        return
      }

      setFields(result.data.fields)
      setMapping(withLanguages(createMapping(inspected, objects, result.data), result.data.fields, languages))
    })
  }

  // a column's transformations run on the stored file as it is read now
  const transformationSource = useMemo((): TransformationSource | null => {
    if (phase.kind !== 'inspected' || phase.inspected.fileId === undefined || phase.inspected.fileId === null ||
      objects.classId === undefined) {
      return null
    }

    return { fileId: phase.inspected.fileId, classId: objects.classId, settings: settingsOf(phase.inspected) }
  }, [phase, objects.classId])

  const canGoOn = step === 0
    ? !isInspecting && canContinue(phase, objects)
    : mapping !== null && canContinueMapping(mapping, fields)

  const onRemoveFile = (): void => {
    deleteStoredFile()
    reset()
  }

  const close = (): void => {
    deleteStoredFile()
    reset()
    onClose()
  }

  // an upload does not outlive the dialog
  useEffect(() => () => { upload.current?.abort() }, [])

  return (
    <Modal
      footer={ (
        <ModalFooter divider>
          { step === 0
            ? <Button onClick={ close }>{ t('data-importer.ad-hoc-import.cancel') }</Button>
            : <Button onClick={ () => { setStep(step - 1) } }>{ t('data-importer.ad-hoc-import.back') }</Button> }
          { step < 2 && (
            <Button
              disabled={ !canGoOn }
              onClick={ () => { step === 0 ? toMapping() : setStep(step + 1) } }
              type="primary"
            >
              { t('data-importer.ad-hoc-import.next') }
            </Button>
          ) }
        </ModalFooter>
      ) }
      maskClosable={ false }
      onCancel={ close }
      open
      title={ t('data-importer.ad-hoc-import.title', {
        path: titlePath(target),
        interpolation: { escapeValue: false }
      }) }
      width={ DIALOG_WIDTH }
    >
      <div style={ { maxWidth: STEPS_WIDTH, marginBottom: 16 } }>
        <Steps
          current={ step }
          items={ STEPS.map((name) => ({ title: t(`data-importer.ad-hoc-import.step.${name}`) })) }
          size="small"
        />
      </div>
      { step === 0 && (
        <FileStep
          isInspecting={ isInspecting }
          objects={ objects }
          onFile={ onFile }
          onObjectsChange={ setObjects }
          onRemoveFile={ onRemoveFile }
          onSettingsChange={ onSettingsChange }
          phase={ phase }
        />
      ) }
      { step === 1 && objects.classId !== undefined && (
        <MappingStep
          classId={ objects.classId }
          fields={ fields }
          isLoading={ isLoadingMapping }
          mapping={ mapping }
          onChange={ setMapping }
          source={ transformationSource }
        />
      ) }
    </Modal>
  )
}
