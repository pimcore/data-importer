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
import { Button, Modal, ModalFooter, Steps } from '@pimcore/studio-ui-bundle/components'
import { ApiError, GeneralError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import {
  useBundleDataImporterAdHocImportDeleteFileMutation,
  useBundleDataImporterAdHocImportInspectFileMutation
} from '../data-importer-api-slice-enhanced'
import { type BundleDataImporterAdHocImportFileSettings } from '../data-importer-api-slice.gen'
import { type AdHocImportTarget } from './dialog-store'
import { FileStep } from './file-step/file-step'
import {
  canContinue,
  hasAcceptedExtension,
  initialObjects,
  keepMatchColumn,
  refusedForFormat,
  type FilePhase,
  type InspectedFile,
  type ObjectsSettings
} from './file-step/file-step-state'
import { UploadAborted, UploadFailed, uploadFile, type RunningUpload } from './file-step/upload-file'

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
  const [phase, setPhase] = useState<FilePhase>({ kind: 'empty' })
  const [objects, setObjects] = useState<ObjectsSettings>(initialObjects)
  const [step, setStep] = useState(0)
  const [isInspecting, setIsInspecting] = useState(false)

  const [inspectFile] = useBundleDataImporterAdHocImportInspectFileMutation()
  const [deleteFile] = useBundleDataImporterAdHocImportDeleteFileMutation()

  const upload = useRef<RunningUpload | null>(null)
  // only the answer to the latest settings counts
  const latestInspection = useRef(0)

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
    setIsInspecting(false)
    setPhase({ kind: 'empty' })
  }

  const applyInspected = (inspected: InspectedFile): void => {
    setObjects((current) => keepMatchColumn(current, inspected))
  }

  const onFile = (file: File): void => {
    const info = { name: file.name, size: file.size }

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
      applyInspected(inspected)
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

  const onSettingsChange = (settings: BundleDataImporterAdHocImportFileSettings): void => {
    if (phase.kind !== 'inspected' || phase.inspected.fileId === undefined || phase.inspected.fileId === null) {
      return
    }

    const inspection = ++latestInspection.current
    setIsInspecting(true)

    void inspectFile({ fileId: phase.inspected.fileId, bundleDataImporterAdHocImportFileSettings: settings }).then((result) => {
      if (inspection !== latestInspection.current) {
        return
      }
      setIsInspecting(false)

      if ('error' in result && result.error !== undefined) {
        trackError(new ApiError(result.error))
        // the file is gone: start over
        setPhase({ kind: 'empty' })
        return
      }

      const inspected = result.data
      applyInspected(inspected)
      setPhase((current) => current.kind === 'inspected' ? { ...current, inspected } : current)
    })
  }

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
          { step === 0 && (
            <Button
              disabled={ isInspecting || !canContinue(phase, objects) }
              onClick={ () => { setStep(step + 1) } }
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
    </Modal>
  )
}
