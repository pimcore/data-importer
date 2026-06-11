/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useEffect, useRef, useState } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Modal, Button, IconButton, Panel, Flex } from '@pimcore/studio-ui-bundle/components'
import { type InterpreterConfig, type LoaderConfig, type ResolverConfig, type ProcessingConfig, type MappingConfigItem, type TransformationPipelineItem, type ClassAttribute } from '../../../../types'
import { useBundleDataImporterConfigCalculateTransformationResultTypeQuery } from '../../../../data-importer-api-slice.gen'
import { StepSource } from './step-source/step-source'
import { StepTransformations } from './step-transformations/step-transformations'
import { StepTarget } from './step-target/step-target'
import { useStyles } from './advanced-mapping-modal.styles'
import { useAutoRecalculateType } from './hooks/use-auto-recalculate-type'
import { ResultPreviewProvider } from './result-preview/result-preview-context'

export { type ClassAttribute } from '../../../../types'
export interface AdvancedMappingModalProps {
  open: boolean
  onClose: () => void
  onSave: (updated: MappingConfigItem) => void
  configName: string
  classId?: string
  item: MappingConfigItem
  columnHeaderOptions: Array<{ value: string, label: string }>
  attributesMap: Record<string, ClassAttribute[]>
  baseConfig?: { loaderConfig?: LoaderConfig, interpreterConfig?: InterpreterConfig, resolverConfig?: ResolverConfig, processingConfig?: ProcessingConfig }
}

export const AdvancedMappingModal = ({
  open,
  onClose,
  onSave,
  configName,
  classId,
  item,
  columnHeaderOptions,
  attributesMap,
  baseConfig
}: AdvancedMappingModalProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  const [localItem, setLocalItem] = useState<MappingConfigItem>(() => structuredClone(item))
  const [expanded, setExpanded] = useState({ source: true, transformations: false, target: false })
  const [previewRefreshToken, setPreviewRefreshToken] = useState(0)
  const [forceRefreshToken, setForceRefreshToken] = useState(0)
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const schedulePreviewRefresh = useCallback((): void => {
    if (debounceRef.current !== null) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      setPreviewRefreshToken(n => n + 1)
      debounceRef.current = null
    }, 800)
  }, [])
  const [calculateTypeRequest, setCalculateTypeRequest] = useState<{
    name: string
    bundleDataImporterCalculateTransformationResultTypeParameters: {
      currentConfig: {
        label?: string
        dataSourceIndex?: string[]
        transformationPipeline?: object[]
        dataTarget?: object
      }
    }
  } | undefined>(undefined)
  const {
    data: calculateTypeResult,
    isFetching: isCalculating,
    error: calculateTypeError,
    refetch: refetchCalculateType
  } = useBundleDataImporterConfigCalculateTransformationResultTypeQuery(
    calculateTypeRequest!,
    {
      skip: calculateTypeRequest === undefined,
      refetchOnMountOrArgChange: false
    }
  )

  useEffect(() => {
    if (calculateTypeResult === undefined) return
    setLocalItem(prev => ({ ...prev, transformationResultType: calculateTypeResult.type }))
  }, [calculateTypeResult])
  const calculateTypeErrorDetail = calculateTypeError !== undefined
    ? (calculateTypeError as { data?: { detail?: string } }).data?.detail
    : undefined
  useEffect(() => {
    if (open) {
      setLocalItem(structuredClone(item))
      setExpanded({ source: true, transformations: false, target: false })
    }
  }, [open, item])

  const openSection = (key: keyof typeof expanded): void => {
    setExpanded(prev => ({
      source: false,
      transformations: false,
      target: false,
      [key]: !prev[key]
    }))
  }

  const pipeline = localItem.transformationPipeline ?? []

  const updatePipeline = (next: TransformationPipelineItem[]): void => {
    setLocalItem(prev => ({ ...prev, transformationPipeline: next }))
    schedulePreviewRefresh()
  }

  const updateDataSourceIndex = (v: string[]): void => {
    setLocalItem(prev => ({ ...prev, dataSourceIndex: v }))
    schedulePreviewRefresh()
  }

  const localItemRef = useRef(localItem)
  localItemRef.current = localItem

  const { mergedAttributesMap, isFetchingExtraAttributes } = useAutoRecalculateType({
    open,
    configName,
    classId,
    localItem,
    localItemRef,
    attributesMap,
    setCalculateTypeRequest
  })

  const recalculateType = useCallback(async (): Promise<void> => {
    const current = localItemRef.current
    const nextRequest = {
      name: configName,
      bundleDataImporterCalculateTransformationResultTypeParameters: {
        currentConfig: {
          label: current.label,
          dataSourceIndex: current.dataSourceIndex,
          transformationPipeline: current.transformationPipeline as object[] | undefined,
          dataTarget: current.dataTarget as object | undefined
        }
      }
    }

    const isSameRequest = JSON.stringify(calculateTypeRequest) === JSON.stringify(nextRequest)
    setCalculateTypeRequest(nextRequest)

    if (isSameRequest) {
      try {
        await refetchCalculateType()
      } catch {
        // ignore
      }
    }
  }, [configName, calculateTypeRequest, refetchCalculateType])

  const handleRefreshAll = useCallback((): void => {
    void recalculateType()
    setForceRefreshToken(n => n + 1)
  }, [recalculateType])

  const handleSave = (): void => {
    onSave(localItem)
    onClose()
  }

  return (
    <Modal
      footer={ (
        <Flex
          align="center"
          className={ styles.footer }
          gap="extra-small"
          justify="flex-end"
        >
          <IconButton
            disabled={ isCalculating }
            icon={ { value: 'refresh' } }
            onClick={ handleRefreshAll }
            tooltip={ { title: t('data-importer.mapping.advanced-modal.refresh-all-previews') } }
          />
          <Button
            onClick={ handleSave }
            type="primary"
          >
            { t('data-importer.mapping.advanced-modal.save') }
          </Button>
        </Flex>
      ) }
      onCancel={ onClose }
      open={ open }
      title={ t('data-importer.mapping.advanced-modal.title') }
      width={ 996 }
    >
      <ResultPreviewProvider
        baseConfig={ baseConfig }
        calculateTypeError={ calculateTypeErrorDetail }
        configName={ configName }
        currentMappingItem={ localItem }
        forceRefreshToken={ forceRefreshToken }
        isFetchingAttributes={ isFetchingExtraAttributes || isCalculating }
        previewRefreshToken={ previewRefreshToken }
      >
        <Flex
          gap="small"
          vertical
        >
          <div className={ styles.sectionPanel }>
            <Panel
              active={ expanded.source }
              collapsible
              onChange={ () => { openSection('source') } }
              theme="default"
              title={ (
                <>
                  <span className={ styles.stepBadge }>1</span>
                  { t('data-importer.mapping.advanced-modal.step-source') }
                </>
              ) }
            >
              <StepSource
                columnHeaderOptions={ columnHeaderOptions }
                configName={ configName }
                dataSourceIndex={ localItem.dataSourceIndex ?? [] }
                forceRefreshToken={ forceRefreshToken }
                onDataSourceIndexChange={ updateDataSourceIndex }
              />
            </Panel>
          </div>

          <div className={ styles.sectionPanel }>
            <Panel
              active={ expanded.transformations }
              collapsible
              onChange={ () => { openSection('transformations') } }
              theme="default"
              title={ (
                <>
                  <span className={ styles.stepBadge }>2</span>
                  { t('data-importer.mapping.advanced-modal.step-transformations') }
                </>
              ) }
            >
              <StepTransformations
                columnHeaderOptions={ columnHeaderOptions }
                dataSourceIndex={ localItem.dataSourceIndex ?? [] }
                onDataSourceIndexChange={ updateDataSourceIndex }
                onPipelineChange={ updatePipeline }
                pipeline={ pipeline }
              />
            </Panel>
          </div>

          <div className={ styles.sectionPanel }>
            <Panel
              active={ expanded.target }
              collapsible
              onChange={ () => { openSection('target') } }
              theme="default"
              title={ (
                <>
                  <span className={ styles.stepBadge }>3</span>
                  { t('data-importer.mapping.advanced-modal.step-target') }
                </>
              ) }
            >
              <StepTarget
                attributesMap={ mergedAttributesMap }
                classId={ classId }
                dataTarget={ localItem.dataTarget }
                onDataTargetChange={ (dataTarget) => { setLocalItem(prev => ({ ...prev, dataTarget })) } }
                transformationResultType={ localItem.transformationResultType }
              />
            </Panel>
          </div>
        </Flex>
      </ResultPreviewProvider>
    </Modal>
  )
}
