/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

/* eslint-disable max-lines */
import React, { useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Alert, Modal, Steps } from '@pimcore/studio-ui-bundle/components'
import { ApiError, GeneralError, trackError } from '@pimcore/studio-ui-bundle/modules/app'
import {
  useBundleDataImporterAdHocImportDeleteFileMutation,
  useBundleDataImporterAdHocImportDiscardRunMutation,
  useBundleDataImporterAdHocImportDryRunMutation,
  useBundleDataImporterAdHocImportInspectFileMutation,
  useBundleDataImporterAdHocImportListWaitingRunsQuery,
  useBundleDataImporterAdHocImportLoadMappingMutation,
  useBundleDataImporterAdHocImportLoadRunQuery,
  useBundleDataImporterAdHocImportMergeLeavingOutMutation,
  useBundleDataImporterAdHocImportStartRunMutation,
  useBundleDataImporterAdHocImportWithdrawRunMutation
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
  type InspectedFile,
  type ObjectsSettings
} from './file-step/file-step-state'
import { UploadAborted, UploadFailed, uploadFile, type RunningUpload } from './file-step/upload-file'
import { canContinueMapping, createMapping, mappingBasis, withSamples, type Mapping, type MappingField } from './mapping-step/mapping-state'
import { withLanguages } from './mapping-step/mapping-choices'
import { MappingStep } from './mapping-step/mapping-step'
import { findConfiguration } from './mapping-step/configuration-state'
import { transformationSourceOf } from './mapping-step/transformations-modal'
import { useConfigurationMapping } from './mapping-step/use-import-configurations'
import { dryRunParameters, previewCounts, type DryRun, type DryRunParameters, type ErrorMode } from './preview-step/preview-state'
import { PreviewStep } from './preview-step/preview-step'
import { isDone, isSettled } from './import-step/import-state'
import { ImportStep } from './import-step/import-step'
import { useLanguages } from './mapping-step/use-languages'
import { ImportFooter, PreviewFooter, StepFooter } from './dialog-footers'

const DIALOG_WIDTH = 1160
const STEPS_WIDTH = 720

const STEPS = ['file', 'mapping', 'preview', 'import']
const IMPORT_STEP = 3
const RUN_POLLING_MS = 1000

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
  const [dryRun, setDryRun] = useState<DryRun | null>(null)
  const [isDryRunFailed, setIsDryRunFailed] = useState(false)
  const [errorMode, setErrorMode] = useState<ErrorMode>('skip')
  // the file of the run started, once the server has answered
  const [runFileId, setRunFileId] = useState<string | null>(null)
  // stops asking once the run has ended or waits for a decision; the last answer stays
  const [runPolling, setRunPolling] = useState(RUN_POLLING_MS)
  // the file of an import into this folder that waited for a decision when the dialog opened
  const [waitingFile, setWaitingFile] = useState<string | undefined>(undefined)
  const [isDeciding, setIsDeciding] = useState(false)

  const [inspectFile] = useBundleDataImporterAdHocImportInspectFileMutation()
  const [deleteFile] = useBundleDataImporterAdHocImportDeleteFileMutation()
  const [loadMapping] = useBundleDataImporterAdHocImportLoadMappingMutation()
  const [runDry] = useBundleDataImporterAdHocImportDryRunMutation()
  const [startRun] = useBundleDataImporterAdHocImportStartRunMutation()
  const [discardRun] = useBundleDataImporterAdHocImportDiscardRunMutation()
  const [mergeLeavingOut] = useBundleDataImporterAdHocImportMergeLeavingOutMutation()
  const [withdrawRun] = useBundleDataImporterAdHocImportWithdrawRunMutation()
  const { data: run, fulfilledTimeStamp: runAnsweredAt, refetch: refetchRun } = useBundleDataImporterAdHocImportLoadRunQuery(
    { fileId: runFileId ?? '' },
    { skip: runFileId === null, pollingInterval: runPolling }
  )
  // asked anew on every open: a list cached from an earlier open may name an import decided since
  const { data: waitingRuns, isFetching: isFetchingWaitingRuns } = useBundleDataImporterAdHocImportListWaitingRunsQuery(
    { folderId: target.folderId },
    { refetchOnMountOrArgChange: true }
  )
  const configurationMapping = useConfigurationMapping(phase.kind === 'inspected' ? phase.inspected : undefined)

  const upload = useRef<RunningUpload | null>(null)
  // only the answer to the latest settings counts
  const latestInspection = useRef(0)
  // a change made while an answer is pending builds on what was asked for, not on the last answer
  const requestedSettings = useRef<BundleDataImporterAdHocImportFileSettings | null>(null)
  // only the mapping asked for last counts
  const latestMapping = useRef(0)
  // only the dry run asked for last counts
  const latestDryRun = useRef(0)

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

  const inspectedFileId = (): string | null => phase.kind === 'inspected' ? phase.inspected.fileId ?? null : null

  const parametersOfFile = (inspected: InspectedFile): DryRunParameters => dryRunParameters({
    target,
    inspected,
    objects,
    mapWith: configurationMapping.mapWith,
    configuration: findConfiguration(configurationMapping.choice.configurations, configurationMapping.choice.chosen),
    mapping
  })

  // the server checks the file once more and queues its rows; the dialog can be closed from now on
  const toImport = (): void => {
    const fileId = inspectedFileId()
    if (phase.kind !== 'inspected' || fileId === null) {
      return
    }

    setStep(IMPORT_STEP)
    void startRun({
      fileId,
      bundleDataImporterAdHocImportRunParameters: {
        settings: parametersOfFile(phase.inspected),
        errorMode,
        fileName: phase.file.name
      }
    }).then((result) => {
      if ('error' in result && result.error !== undefined) {
        trackError(new ApiError(result.error))
        setStep(2)
        return
      }

      setRunFileId(fileId)
    })
  }

  // every row of the file is checked as the mapping stands now; nothing is written
  const toPreview = (): void => {
    setStep(2)

    const request = ++latestDryRun.current
    setDryRun(null)
    setIsDryRunFailed(false)
    if (phase.kind !== 'inspected' || phase.inspected.fileId === undefined || phase.inspected.fileId === null) {
      return
    }

    void runDry({
      fileId: phase.inspected.fileId,
      bundleDataImporterAdHocImportDryRunParameters: parametersOfFile(phase.inspected)
    }).then((result) => {
      if (request !== latestDryRun.current) {
        return
      }

      if ('error' in result && result.error !== undefined) {
        trackError(new ApiError(result.error))
        setIsDryRunFailed(true)
        return
      }

      setDryRun(result.data)
    })
  }

  const importCount = dryRun === null ? 0 : previewCounts(dryRun, errorMode).imported

  const transformationSource = useMemo(() => transformationSourceOf(phase, objects.classId), [phase, objects.classId])

  const canMapOn = (): boolean => configurationMapping.mapWith === 'configuration'
    ? configurationMapping.canContinue
    : mapping !== null && canContinueMapping(mapping, fields)

  const canGoOn = step === 0
    ? !isInspecting && canContinue(phase, objects)
    : canMapOn()

  const onRemoveFile = (): void => {
    deleteStoredFile()
    reset()
  }

  const back = (): void => {
    // an answer still on its way belongs to the step left
    latestDryRun.current += 1
    setStep(step - 1)
  }

  const close = (): void => {
    if (step === IMPORT_STEP) {
      // a running import goes on, with its file; once it has ended, nothing of it is needed any more
      if (runFileId !== null && isDone(run)) {
        void discardRun({ fileId: runFileId }).then((result) => {
          if ('error' in result && result.error !== undefined) {
            trackError(new ApiError(result.error))
          }
        })
      }
      onClose()
      return
    }

    deleteStoredFile()
    reset()
    onClose()
  }

  // the server answers the run as its merge starts; the dialog follows it from there
  const mergeWithoutFailed = (): void => {
    if (runFileId === null) {
      return
    }

    setIsDeciding(true)
    void mergeLeavingOut({ fileId: runFileId }).then((result) => {
      setIsDeciding(false)
      if ('error' in result && result.error !== undefined) {
        trackError(new ApiError(result.error))
        return
      }

      setRunPolling(RUN_POLLING_MS)
      void refetchRun()
    })
  }

  // the branch goes, the file stays: the next import opens a branch of its own
  const backToMapping = (): void => {
    if (runFileId === null) {
      return
    }

    setIsDeciding(true)
    void withdrawRun({ fileId: runFileId }).then((result) => {
      setIsDeciding(false)
      if ('error' in result && result.error !== undefined) {
        trackError(new ApiError(result.error))
        return
      }

      setRunFileId(null)
      setRunPolling(RUN_POLLING_MS)
      setStep(1)
    })
  }

  // nothing is written, and the file and its downloads go
  const discardImport = (): void => {
    if (runFileId === null) {
      return
    }

    setIsDeciding(true)
    void discardRun({ fileId: runFileId }).then((result) => {
      setIsDeciding(false)
      if ('error' in result && result.error !== undefined) {
        trackError(new ApiError(result.error))
        return
      }

      onClose()
    })
  }

  const footer = (): React.JSX.Element => {
    if (step === 2) {
      return (
        <PreviewFooter
          errorMode={ errorMode }
          importCount={ importCount }
          onBack={ back }
          onErrorModeChange={ setErrorMode }
          onImport={ toImport }
        />
      )
    }

    if (step === IMPORT_STEP) {
      return (
        <ImportFooter
          decision={ {
            isDeciding,
            onBackToMapping: phase.kind === 'inspected' ? backToMapping : undefined,
            onDiscard: discardImport,
            onMerge: mergeWithoutFailed
          } }
          onClose={ close }
          run={ run }
          runFileId={ runFileId }
        />
      )
    }

    return (
      <StepFooter
        canGoOn={ canGoOn }
        isFirstStep={ step === 0 }
        onBack={ back }
        onClose={ close }
        onNext={ step === 0 ? toMapping : toPreview }
      />
    )
  }

  // every answer counts: a merge refused again answers 'decision' as before
  useEffect(() => {
    if (isSettled(run)) {
      setRunPolling(0)
    }
  }, [run?.state, runAnsweredAt])

  // an import into this folder waits for the user's decision: the dialog opens on it
  useEffect(() => {
    const waiting = isFetchingWaitingRuns ? undefined : waitingRuns?.items[0]
    if (waiting !== undefined && runFileId === null && step === 0 && phase.kind === 'empty') {
      setWaitingFile(waiting.fileName)
      setRunFileId(waiting.fileId)
      setStep(IMPORT_STEP)
    }
  }, [waitingRuns, isFetchingWaitingRuns])

  // an upload does not outlive the dialog
  useEffect(() => () => { upload.current?.abort() }, [])

  return (
    <Modal
      footer={ footer() }
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
          current={ isDone(run) ? STEPS.length : step }
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
          configuration={ configurationMapping.choice }
          fields={ fields }
          isLoading={ isLoadingMapping }
          mapWith={ configurationMapping.mapWith }
          mapping={ mapping }
          onChange={ setMapping }
          onMapWithChange={ configurationMapping.setMapWith }
          source={ transformationSource }
        />
      ) }
      { step === IMPORT_STEP && (phase.kind === 'inspected' || runFileId !== null) && (
        <ImportStep
          fileId={ runFileId ?? inspectedFileId() ?? '' }
          run={ run }
          total={ importCount }
          waitingFile={ waitingFile }
        />
      ) }
      { step === 2 && (isDryRunFailed
        ? (
          <Alert
            message={ t('data-importer.ad-hoc-import.preview.failed') }
            showIcon
            type="error"
          />
          )
        : (
          <PreviewStep
            dryRun={ dryRun }
            errorMode={ errorMode }
          />
          )) }
    </Modal>
  )
}
