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
import { Button, Flex, Input, Select } from '@pimcore/studio-ui-bundle/components'
import { type MappingConfigItem } from '../../../../../types'
import { useStyles } from './step-target.styles'
import { ErrorBox } from '../error-box/error-box'
import { StepTargetWriteSettings } from './step-target-write-settings'

export interface StepTargetFieldsProps {
  dataTarget?: MappingConfigItem['dataTarget']
  attributeOptions: Array<{ value: string, label: string }>
  languageOptions: Array<{ value: string, label: string }>
  isClassificationStore: boolean
  isClassificationStoreBatch: boolean
  isFetchingClassificationStoreAttributes: boolean
  hasTypeError: boolean
  typeErrorMessage?: string
  isLocalized: boolean
  showWriteSettings: boolean
  showOverwriteMode: boolean
  keyLabel: string
  canOpenKeyModal: boolean
  setKeyModalOpen: (open: boolean) => void
  onDataTargetChange: (dataTarget: MappingConfigItem['dataTarget']) => void
}

export const StepTargetFields = ({
  dataTarget,
  attributeOptions,
  languageOptions,
  isClassificationStore,
  isClassificationStoreBatch,
  isFetchingClassificationStoreAttributes,
  hasTypeError,
  typeErrorMessage,
  isLocalized,
  showWriteSettings,
  showOverwriteMode,
  keyLabel,
  canOpenKeyModal,
  setKeyModalOpen,
  onDataTargetChange
}: StepTargetFieldsProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  return (
    <Flex
      className={ styles.fieldsContainer }
      gap={ 6 }
      vertical
    >
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
        <ErrorBox>{ typeErrorMessage }</ErrorBox>
      ) }

      { !hasTypeError && (
        <>
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

          { isClassificationStore && (
            <div>
              <div className={ styles.fieldLabel }>
                { t('data-importer.mapping.advanced-modal.step-target.classification-store-key') }
              </div>
              <Flex
                align="center"
                gap="extra-small"
              >
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
              </Flex>
            </div>
          ) }

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

      <StepTargetWriteSettings
        dataTarget={ dataTarget }
        hasTypeError={ hasTypeError }
        onDataTargetChange={ onDataTargetChange }
        showOverwriteMode={ showOverwriteMode }
        showWriteSettings={ showWriteSettings }
      />
    </Flex>
  )
}
