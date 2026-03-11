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
import { Button, Input, Select, Switch, Text } from '@pimcore/studio-ui-bundle/components'
import { type MappingConfigItem, type ClassAttribute, resolveAttrMapKey, type InterpreterConfig, type LoaderConfig, type ResolverConfig, type ProcessingConfig } from '../../../../../types'
import {
  useBundleDataImporterClassificationstoreLoadAttributesQuery,
  useBundleDataImporterClassificationstoreLoadKeyNameQuery
} from '../../../../../data-importer-api-slice.gen'
import { PreviewPanel } from '../preview-panel/preview-panel'
import { useSharedStepStyles } from '../step-shared.styles'
import { useStyles } from './step-target.styles'
import { ClassificationStoreKeyModal } from './classification-store-key-modal/classification-store-key-modal'

export interface StepTargetProps {
  attributesMap: Record<string, ClassAttribute[]>
  transformationResultType?: string
  dataTarget?: MappingConfigItem['dataTarget']
  languageOptions: Array<{ value: string, label: string }>
  classId?: string
  configName: string
  previewRefreshToken: number
  /** Live snapshot of the full mapping item — forwarded to PreviewPanel */
  currentMappingItem?: MappingConfigItem
  /** Saved loaderConfig + interpreterConfig + resolverConfig — needed for the preview backend call */
  baseConfig?: { loaderConfig?: LoaderConfig, interpreterConfig?: InterpreterConfig, resolverConfig?: ResolverConfig, processingConfig?: ProcessingConfig }
  onDataTargetChange: (dataTarget: MappingConfigItem['dataTarget']) => void
  onPrev: () => void
  onConfirm: () => void
}

export const StepTarget = ({
  attributesMap,
  transformationResultType,
  dataTarget,
  languageOptions,
  classId,
  configName,
  previewRefreshToken,
  currentMappingItem,
  baseConfig,
  onDataTargetChange,
  onPrev,
  onConfirm
}: StepTargetProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { styles: shared } = useSharedStepStyles()
  const [keyModalOpen, setKeyModalOpen] = useState(false)

  const prevTransformationResultTypeRef = useRef<string | undefined>(transformationResultType)

  const isDirect = dataTarget?.type === 'direct'
  const isClassificationStore = dataTarget?.type === 'classificationstore'
  const isClassificationStoreBatch = dataTarget?.type === 'classificationstoreBatch'
  const isManyToMany = dataTarget?.type === 'manyToManyRelation'

  const validBatchTypes = useMemo(
    () => new Set(['array', 'quantityValueArray', 'inputQuantityValueArray', 'dateArray']),
    []
  )
  const validManyToManyTypes = useMemo(
    () => new Set(['advancedDataObject', 'dataObjectArray', 'assetArray', 'advancedAssetArray']),
    []
  )

  const isBatchTypeValid = !isClassificationStoreBatch || validBatchTypes.has(transformationResultType ?? '')
  const isManyToManyTypeValid = !isManyToMany || validManyToManyTypes.has(transformationResultType ?? '')
  const hasTypeError = !isBatchTypeValid || !isManyToManyTypeValid

  const attrMapKey = resolveAttrMapKey(transformationResultType)
  const defaultAttributes: ClassAttribute[] = attributesMap[attrMapKey] ?? []

  const {
    data: classificationStoreAttributesResponse,
    isFetching: isFetchingClassificationStoreAttributes
  } = useBundleDataImporterClassificationstoreLoadAttributesQuery(
    { classId: classId ?? '' },
    {
      skip: classId === undefined || (!isClassificationStore && !isClassificationStoreBatch)
    }
  )

  const classificationStoreAttributes: ClassAttribute[] = useMemo(() => {
    const rawAttributes = classificationStoreAttributesResponse?.attributes ?? []
    return (rawAttributes as Array<{ key?: string, title?: string, name?: string, localized?: boolean }>)
      .filter((attribute) => attribute.key !== undefined)
      .map((attribute) => ({
        key: attribute.key ?? '',
        title: attribute.title ?? attribute.name ?? attribute.key ?? '',
        localized: attribute.localized ?? false
      }))
  }, [classificationStoreAttributesResponse?.attributes])

  const attributes = isClassificationStore || isClassificationStoreBatch
    ? classificationStoreAttributes
    : defaultAttributes

  const attributeOptions = attributes.map(a => ({ value: a.key, label: a.title }))
  const isLocalized = attributes.find(a => a.key === dataTarget?.settings?.fieldName)?.localized ?? false

  const writeIfNotEmpty = dataTarget?.settings?.writeIfTargetIsNotEmpty ?? false
  const showOverwriteMode = isManyToMany && writeIfNotEmpty
  const showWriteSettings = isDirect || isManyToMany

  const keyId = dataTarget?.settings?.keyId as string | undefined
  const canOpenKeyModal =
    classId !== undefined &&
    dataTarget?.settings?.fieldName !== undefined &&
    transformationResultType !== undefined &&
    transformationResultType !== '' &&
    !hasTypeError

  const { data: keyNameResponse } = useBundleDataImporterClassificationstoreLoadKeyNameQuery(
    { keyId: keyId ?? '' },
    { skip: keyId === undefined || keyId === '' }
  )

  const keyLabel = useMemo(() => {
    if (keyId === undefined || keyId === '') {
      return t('data-importer.mapping.advanced-modal.step-target.classification-store-key-placeholder')
    }

    if (keyNameResponse?.groupName !== undefined && keyNameResponse?.keyName !== undefined) {
      return t('data-importer.mapping.advanced-modal.step-target.classification-store-key-in-group', {
        key: keyNameResponse.keyName,
        group: keyNameResponse.groupName
      })
    }

    return keyId
  }, [keyId, keyNameResponse?.groupName, keyNameResponse?.keyName, t])

  const typeErrorMessage = useMemo(() => {
    if (!isBatchTypeValid) {
      return t('data-importer.mapping.advanced-modal.step-target.type-error.classificationstoreBatch')
    }
    if (!isManyToManyTypeValid) {
      return t('data-importer.mapping.advanced-modal.step-target.type-error.manyToManyRelation')
    }
    return undefined
  }, [isBatchTypeValid, isManyToManyTypeValid, t])

  // When writeIfTargetIsNotEmpty is turned off, clear overwriteMode
  useEffect(() => {
    if (!showWriteSettings) return

    if (!writeIfNotEmpty && (dataTarget?.settings?.overwriteMode !== undefined || dataTarget?.settings?.writeIfSourceIsEmpty === true)) {
      onDataTargetChange({
        ...dataTarget,
        settings: {
          ...dataTarget.settings,
          overwriteMode: undefined,
          writeIfSourceIsEmpty: false
        }
      })
    }
  }, [writeIfNotEmpty, showWriteSettings])

  // Match ExtJS behavior: changing transformation result type invalidates previously selected classification store key.
  useEffect(() => {
    if (prevTransformationResultTypeRef.current === transformationResultType) return
    prevTransformationResultTypeRef.current = transformationResultType

    if ((isClassificationStore || isClassificationStoreBatch) && dataTarget?.settings?.keyId !== undefined) {
      onDataTargetChange({
        ...dataTarget,
        settings: {
          ...dataTarget.settings,
          keyId: undefined
        }
      })
    }
  }, [transformationResultType, isClassificationStore, isClassificationStoreBatch])

  return (
    <div className={ styles.twoColumnLayout }>

      {/* LEFT: Data Target config */}
      <div className={ styles.leftColumn }>
        {/* Header bar */}
        <div className={ styles.leftHeader }>
          <Text
            className={ styles.leftHeaderTitle }
            strong
          >
            { t('data-importer.mapping.advanced-modal.step-target') }
          </Text>
        </div>

        {/* Fields */}
        <div className={ styles.fieldsContainer }>
          {/* Type */}
          <div>
            <div className={ styles.fieldLabel }>
              { t('data-importer.mapping.advanced-modal.step-target.type') }
            </div>
            <div className={ styles.selectSkeletonWrapper }>
              <Select
                className={ styles.selectFull }
                onChange={ (v: string) => {
                  const previousType = dataTarget?.type
                  const isSwitchingToClassificationStore =
                    (v === 'classificationstore' || v === 'classificationstoreBatch') &&
                    (previousType === 'direct' || previousType === 'manyToManyRelation')

                  const nextSettings = {
                    ...dataTarget?.settings,
                    overwriteMode: v === 'manyToManyRelation' ? dataTarget?.settings?.overwriteMode : undefined,
                    keyId: (v === 'classificationstore' || v === 'classificationstoreBatch') ? dataTarget?.settings?.keyId : undefined,
                    fieldName: isSwitchingToClassificationStore ? undefined : dataTarget?.settings?.fieldName,
                    language: isSwitchingToClassificationStore ? undefined : dataTarget?.settings?.language,
                    writeIfTargetIsNotEmpty: v === 'direct' || v === 'manyToManyRelation'
                      ? (dataTarget?.settings?.writeIfTargetIsNotEmpty ?? true)
                      : dataTarget?.settings?.writeIfTargetIsNotEmpty,
                    writeIfSourceIsEmpty: v === 'direct' || v === 'manyToManyRelation'
                      ? (dataTarget?.settings?.writeIfSourceIsEmpty ?? true)
                      : dataTarget?.settings?.writeIfSourceIsEmpty
                  }

                  onDataTargetChange({
                    ...dataTarget,
                    type: v,
                    settings: nextSettings
                  })
                } }
                options={ [
                  { value: 'direct', label: t('data-importer.mapping.item.data-target.type.direct') },
                  { value: 'classificationstore', label: t('data-importer.mapping.item.data-target.type.classificationstore') },
                  { value: 'classificationstoreBatch', label: t('data-importer.mapping.item.data-target.type.classificationstoreBatch') },
                  { value: 'manyToManyRelation', label: t('data-importer.mapping.item.data-target.type.manyToManyRelation') }
                ] }
                value={ dataTarget?.type }
              />
            </div>
          </div>

          { hasTypeError && (
            <div className={ styles.typeError }>
              { typeErrorMessage }
            </div>
          ) }

          { !hasTypeError && (
            <>
              {/* Field name */}
              <div>
                <div className={ styles.fieldLabel }>
                  { t('data-importer.mapping.advanced-modal.step-target.field-name') }
                </div>
                <div className={ styles.selectSkeletonWrapper }>
                  <Select
                    className={ styles.selectFull }
                    loadingSkeleton={ isFetchingClassificationStoreAttributes }
                    onChange={ (v: string) => {
                      onDataTargetChange({
                        ...dataTarget,
                        settings: {
                          ...dataTarget?.settings,
                          fieldName: v,
                          language: undefined,
                          keyId: isClassificationStore || isClassificationStoreBatch ? undefined : dataTarget?.settings?.keyId
                        }
                      })
                    } }
                    options={ attributeOptions }
                    showSearch
                    value={ dataTarget?.settings?.fieldName }
                  />
                </div>
              </div>

              {/* Classification store key selector */}
              { isClassificationStore && (
                <div>
                  <div className={ styles.fieldLabel }>
                    { t('data-importer.mapping.advanced-modal.step-target.classification-store-key') }
                  </div>
                  <div className={ styles.classificationStoreKeyRow }>
                    <Input
                      className={ styles.classificationStoreKeyInput }
                      readOnly
                      value={ keyLabel }
                    />
                    <Button
                      disabled={ !canOpenKeyModal }
                      onClick={ () => { setKeyModalOpen(true) } }
                      type="default"
                    >
                      { t('common.search') }
                    </Button>
                  </div>
                </div>
              ) }

              {/* Language selector — only when the selected field is localized */}
              { isLocalized && (
                <div>
                  <div className={ styles.fieldLabel }>
                    { t('data-importer.mapping.item.data-target.language-placeholder') }
                  </div>
                  <div className={ styles.selectSkeletonWrapper }>
                    <Select
                      className={ styles.selectFull }
                      onChange={ (v: string) => {
                        onDataTargetChange({
                          ...dataTarget,
                          settings: { ...dataTarget?.settings, language: v }
                        })
                      } }
                      options={ languageOptions }
                      showSearch
                      value={ dataTarget?.settings?.language }
                    />
                  </div>
                </div>
              ) }
            </>
          ) }

          {/* Overwrite / write settings */}
          { showWriteSettings && !hasTypeError && (
            <>
              <div className={ styles.overwriteLabel }>
                { t('data-importer.mapping.advanced-modal.step-target.overwrite') }
              </div>

              {/* Switch: Write if target is not empty */}
              <Switch
                checked={ dataTarget?.settings?.writeIfTargetIsNotEmpty ?? true }
                labelRight={ <span className={ styles.switchLabel }>{ t('data-importer.mapping.advanced-modal.write-if-target-not-empty') }</span> }
                onChange={ (checked) => {
                  onDataTargetChange({
                    ...dataTarget,
                    settings: {
                      ...dataTarget?.settings,
                      writeIfTargetIsNotEmpty: checked,
                      writeIfSourceIsEmpty: !!checked
                    }
                  })
                } }
                size="small"
                tooltip={ t('data-importer.mapping.advanced-modal.step-target.write-if-target-not-empty.tooltip') }
              />

              {/* Overwrite mode — only for manyToManyRelation when writeIfTargetIsNotEmpty is true */}
              { showOverwriteMode && (
                <div>
                  <div className={ styles.fieldLabel }>
                    { t('data-importer.mapping.advanced-modal.step-target.overwrite-mode') }
                  </div>
                  <div className={ styles.selectSkeletonWrapper }>
                    <Select
                      className={ styles.selectFull }
                      onChange={ (v: string) => {
                        onDataTargetChange({
                          ...dataTarget,
                          settings: { ...dataTarget?.settings, overwriteMode: v }
                        })
                      } }
                      options={ [
                        { value: 'replace', label: t('data-importer.mapping.advanced-modal.step-target.overwrite-mode.replace') },
                        { value: 'merge', label: t('data-importer.mapping.advanced-modal.step-target.overwrite-mode.merge') }
                      ] }
                      value={ dataTarget?.settings?.overwriteMode }
                    />
                  </div>
                </div>
              ) }

              {/* Switch: Write if source is empty */}
              <Switch
                checked={ dataTarget?.settings?.writeIfSourceIsEmpty ?? true }
                disabled={ !(dataTarget?.settings?.writeIfTargetIsNotEmpty ?? true) }
                labelRight={ <span className={ styles.switchLabel }>{ t('data-importer.mapping.advanced-modal.write-if-source-empty') }</span> }
                onChange={ (checked) => {
                  onDataTargetChange({
                    ...dataTarget,
                    settings: { ...dataTarget?.settings, writeIfSourceIsEmpty: checked }
                  })
                } }
                size="small"
                tooltip={ t('data-importer.mapping.advanced-modal.step-target.write-if-source-empty.tooltip') }
              />
            </>
          ) }
        </div>
      </div>

      {/* RIGHT: Preview Result + buttons */}
      <div className={ styles.rightColumn }>
        {/* Top: Preview Result panel — always mounted to preserve fetched data */}
        <div className={ styles.previewWrapper }>
          <PreviewPanel
            baseConfig={ baseConfig }
            configName={ configName }
            currentMappingItem={ currentMappingItem }
            mode="result"
            refreshToken={ previewRefreshToken }
          />
        </div>

        {/* Bottom: Previous step + Confirm mapping */}
        <div className={ shared.navButtons }>
          <button
            className={ shared.outlineButton }
            onClick={ onPrev }
            type="button"
          >
            { t('data-importer.mapping.advanced-modal.step-target.previous-step') }
          </button>
          <Button
            onClick={ onConfirm }
            type="primary"
          >
            { t('data-importer.mapping.advanced-modal.step-target.confirm-mapping') }
          </Button>
        </div>
      </div>

      { isClassificationStore && classId !== undefined && dataTarget?.settings?.fieldName !== undefined && transformationResultType !== undefined && (
        <ClassificationStoreKeyModal
          classId={ classId }
          fieldName={ dataTarget.settings.fieldName }
          onClose={ () => { setKeyModalOpen(false) } }
          onSelect={ (selectedKeyId) => {
            onDataTargetChange({
              ...dataTarget,
              settings: {
                ...dataTarget?.settings,
                keyId: selectedKeyId
              }
            })
            setKeyModalOpen(false)
          } }
          open={ keyModalOpen }
          transformationResultType={ transformationResultType }
        />
      ) }

    </div>
  )
}
