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
import { Flex, Text } from '@pimcore/studio-ui-bundle/components'
import { type MappingConfigItem, type ClassAttribute, resolveAttrMapKey, type InterpreterConfig, type LoaderConfig, type ResolverConfig, type ProcessingConfig } from '../../../../../types'
import {
  useBundleDataImporterClassificationstoreLoadAttributesQuery,
  useBundleDataImporterClassificationstoreLoadKeyNameQuery
} from '../../../../../data-importer-api-slice.gen'
import { useStyles } from './step-target.styles'
import { ClassificationStoreKeyModal } from './classification-store-key-modal/classification-store-key-modal'
import { StepTargetPreviewActions } from './step-target-preview-actions'
import { StepTargetFields } from './step-target-fields'

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
    <Flex
      className={ styles.twoColumnLayout }
      gap="extra-small"
    >

      {/* LEFT: Data Target config */}
      <Flex
        className={ styles.leftColumn }
        vertical
      >
        {/* Header bar */}
        <Flex
          align="center"
          className={ styles.leftHeader }
        >
          <Text
            className={ styles.leftHeaderTitle }
            strong
          >
            { t('data-importer.mapping.advanced-modal.step-target') }
          </Text>
        </Flex>

        <StepTargetFields
          attributeOptions={ attributeOptions }
          canOpenKeyModal={ canOpenKeyModal }
          dataTarget={ dataTarget }
          hasTypeError={ hasTypeError }
          isClassificationStore={ isClassificationStore }
          isClassificationStoreBatch={ isClassificationStoreBatch }
          isFetchingClassificationStoreAttributes={ isFetchingClassificationStoreAttributes }
          isLocalized={ isLocalized }
          keyLabel={ keyLabel }
          languageOptions={ languageOptions }
          onDataTargetChange={ onDataTargetChange }
          setKeyModalOpen={ setKeyModalOpen }
          showOverwriteMode={ showOverwriteMode }
          showWriteSettings={ showWriteSettings }
          typeErrorMessage={ typeErrorMessage }
        />
      </Flex>

      <StepTargetPreviewActions
        baseConfig={ baseConfig }
        configName={ configName }
        currentMappingItem={ currentMappingItem }
        onConfirm={ onConfirm }
        onPrev={ onPrev }
        previewRefreshToken={ previewRefreshToken }
      />

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

    </Flex>
  )
}
