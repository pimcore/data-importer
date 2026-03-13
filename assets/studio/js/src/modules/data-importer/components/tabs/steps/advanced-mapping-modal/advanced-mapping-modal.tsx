/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useSettings } from '@pimcore/studio-ui-bundle/modules/app'
import { Modal, Button, IconButton, Panel, Flex } from '@pimcore/studio-ui-bundle/components'
import { type InterpreterConfig, type LoaderConfig, type ResolverConfig, type ProcessingConfig, type MappingConfigItem, type TransformationPipelineItem, type ClassAttribute } from '../../../../types'
import { useBundleDataImporterConfigCalculateTransformationResultTypeQuery } from '../../../../data-importer-api-slice.gen'
import { StepSource } from './step-source/step-source'
import { StepTransformations } from './step-transformations/step-transformations'
import { StepTarget } from './step-target/step-target'
import { useStyles } from './advanced-mapping-modal.styles'

export { type ClassAttribute } from '../../../../types'

export interface AdvancedMappingModalProps {
  open: boolean
  onClose: () => void
  onSave: (updated: MappingConfigItem) => void
  configName: string
  classId?: string
  /** The mapping item being edited (read-only snapshot — modal works on a copy) */
  item: MappingConfigItem
  columnHeaderOptions: Array<{ value: string, label: string }>
  /** Class attributes keyed by transformationResultType ('__default__' for plain) */
  attributesMap: Record<string, ClassAttribute[]>
  /** Saved loaderConfig + interpreterConfig + resolverConfig from the form — needed for the preview backend call */
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
  const settings = useSettings()
  const languageOptions = useMemo(
    () => (settings.validLanguages ?? []).map((locale: string) => ({ value: locale, label: locale })),
    [settings.validLanguages]
  )

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

  // Reset local copy when modal opens with a new item
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
        // silently ignore — type stays as-is
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
              onNext={ () => { openSection('transformations') } }
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
              baseConfig={ baseConfig }
              columnHeaderOptions={ columnHeaderOptions }
              configName={ configName }
              currentMappingItem={ localItem }
              dataSourceIndex={ localItem.dataSourceIndex ?? [] }
              forceRefreshToken={ forceRefreshToken }
              onDataSourceIndexChange={ updateDataSourceIndex }
              onNext={ () => { openSection('target') } }
              onPipelineChange={ updatePipeline }
              onPrev={ () => { openSection('source') } }
              pipeline={ pipeline }
              previewRefreshToken={ previewRefreshToken }
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
              attributesMap={ attributesMap }
              baseConfig={ baseConfig }
              classId={ classId }
              configName={ configName }
              currentMappingItem={ localItem }
              dataTarget={ localItem.dataTarget }
              forceRefreshToken={ forceRefreshToken }
              languageOptions={ languageOptions }
              onConfirm={ handleSave }
              onDataTargetChange={ (dataTarget) => { setLocalItem(prev => ({ ...prev, dataTarget })) } }
              onPrev={ () => { openSection('transformations') } }
              previewRefreshToken={ previewRefreshToken }
              transformationResultType={ localItem.transformationResultType }
            />
          </Panel>
        </div>
      </Flex>
    </Modal>
  )
}
