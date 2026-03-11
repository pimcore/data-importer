/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo } from 'react'
import { Select, Form, Input, Switch, ManyToOneRelationPath } from '@pimcore/studio-ui-bundle/components'
import { useTranslation } from '@pimcore/studio-ui-bundle/app'
import { useSettings } from '@pimcore/studio-ui-bundle/modules/app'
import { FieldWidthProvider } from '@pimcore/studio-ui-bundle/modules/element'
import { useClassDefinitionCollectionQuery } from '@pimcore/studio-ui-bundle/api/class-definition'
import { useBundleDataImporterDataTypeLoadClassAttributesQuery } from '../../../../data-importer-api-slice.gen'
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel'
import { StepHeading } from '../step-heading/step-heading'
import { filterByLabel } from '../select-utils'
import type { ClassAttribute } from '../../../../types'

export interface ResolverStepProps {
  configName: string
  columnHeaderOptions: Array<{ value: string, label: string }>
}

function parseClassAttribute (raw: object): ClassAttribute {
  const obj = raw as Record<string, any>
  return {
    key: obj.key ?? obj.name ?? '',
    title: obj.title ?? obj.name ?? obj.key ?? '',
    localized: Boolean(obj.localized ?? false)
  }
}

export const ResolverStep = ({ configName: _configName, columnHeaderOptions }: ResolverStepProps): React.JSX.Element => {
  const { t } = useTranslation()
  const settings = useSettings()

  const languageOptions = useMemo(
    () => (settings.validLanguages ?? []).map((locale: string) => ({ value: locale, label: locale })),
    [settings.validLanguages]
  )

  const { data: classDefinitions, isLoading: isLoadingClasses } = useClassDefinitionCollectionQuery()

  const classOptions = (classDefinitions?.items ?? []).map((cls) => ({
    value: cls.id,
    label: cls.name
  }))

  // Watch values needed for conditional sub-panels
  const dataObjectClassId = Form.useWatch(['resolverConfig', 'dataObjectClassId']) as string | undefined
  const loadingStrategyType = Form.useWatch(['resolverConfig', 'loadingStrategy', 'type']) as string | undefined
  const loadingAttributeName = Form.useWatch(['resolverConfig', 'loadingStrategy', 'settings', 'attributeName']) as string | undefined
  const createLocationType = Form.useWatch(['resolverConfig', 'createLocationStrategy', 'type']) as string | undefined
  const updateLocationType = Form.useWatch(['resolverConfig', 'locationUpdateStrategy', 'type']) as string | undefined
  const createFindStrategy = Form.useWatch(['resolverConfig', 'createLocationStrategy', 'settings', 'findStrategy']) as string | undefined
  const updateFindStrategy = Form.useWatch(['resolverConfig', 'locationUpdateStrategy', 'settings', 'findStrategy']) as string | undefined
  const createFindParentClassId = Form.useWatch(['resolverConfig', 'createLocationStrategy', 'settings', 'attributeDataObjectClassId']) as string | undefined
  const updateFindParentClassId = Form.useWatch(['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeDataObjectClassId']) as string | undefined
  const publishingStrategyType = Form.useWatch(['resolverConfig', 'publishingStrategy', 'type']) as string | undefined
  const createFindParentAttrName = Form.useWatch(['resolverConfig', 'createLocationStrategy', 'settings', 'attributeName']) as string | undefined
  const updateFindParentAttrName = Form.useWatch(['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeName']) as string | undefined

  // Fetch attributes for loading strategy (attribute type) — keyed by resolver class
  const { data: loadingAttrData, isLoading: isLoadingLoadingAttrs } = useBundleDataImporterDataTypeLoadClassAttributesQuery(
    { classId: dataObjectClassId ?? '' },
    { skip: dataObjectClassId === undefined || dataObjectClassId === '' || loadingStrategyType !== 'attribute' }
  )
  const loadingAttributes = useMemo(() => (loadingAttrData?.attributes ?? []).map(parseClassAttribute), [loadingAttrData])
  const loadingAttributeOptions = loadingAttributes.map((a) => ({ value: a.key, label: a.title }))
  const loadingAttrIsLocalized = loadingAttributes.find((a) => a.key === loadingAttributeName)?.localized ?? false

  // Fetch attributes for findParent (create location strategy) — keyed by its own classId
  const { data: createFindParentAttrData, isLoading: isLoadingCreateFindParentAttrs } = useBundleDataImporterDataTypeLoadClassAttributesQuery(
    { classId: createFindParentClassId ?? '', systemRead: true },
    { skip: createFindParentClassId === undefined || createFindParentClassId === '' || createFindStrategy !== 'attribute' }
  )
  const createFindParentAttributes = useMemo(
    () => (createFindParentAttrData?.attributes ?? []).map(parseClassAttribute),
    [createFindParentAttrData]
  )
  const createFindParentAttrOptions = createFindParentAttributes.map((a) => ({ value: a.key, label: a.title }))
  const createFindParentAttrIsLocalized = createFindParentAttributes.find((a) => a.key === createFindParentAttrName)?.localized ?? false

  // Fetch attributes for findParent (update location strategy) — keyed by its own classId
  const { data: updateFindParentAttrData } = useBundleDataImporterDataTypeLoadClassAttributesQuery(
    { classId: updateFindParentClassId ?? '', systemRead: true },
    { skip: updateFindParentClassId === undefined || updateFindParentClassId === '' || updateFindStrategy !== 'attribute' }
  )
  const updateFindParentAttributes = useMemo(
    () => (updateFindParentAttrData?.attributes ?? []).map(parseClassAttribute),
    [updateFindParentAttrData]
  )
  const updateFindParentAttrOptions = updateFindParentAttributes.map((a) => ({ value: a.key, label: a.title }))
  const updateFindParentAttrIsLocalized = updateFindParentAttributes.find((a) => a.key === updateFindParentAttrName)?.localized ?? false

  const loadingStrategyOptions = [
    { value: 'notLoad', label: t('data-importer.resolver.loading-strategy.notLoad') },
    { value: 'id', label: t('data-importer.resolver.loading-strategy.id') },
    { value: 'path', label: t('data-importer.resolver.loading-strategy.path') },
    { value: 'attribute', label: t('data-importer.resolver.loading-strategy.attribute') }
  ]

  const createLocationStrategyOptions = [
    { value: 'staticPath', label: t('data-importer.resolver.location-strategy.staticPath') },
    { value: 'findOrCreateFolder', label: t('data-importer.resolver.location-strategy.findOrCreateFolder') },
    { value: 'findParent', label: t('data-importer.resolver.location-strategy.findParent') },
    { value: 'doNotCreate', label: t('data-importer.resolver.location-strategy.doNotCreate') }
  ]

  const locationUpdateStrategyOptions = [
    { value: 'noChange', label: t('data-importer.resolver.location-strategy.noChange') },
    { value: 'staticPath', label: t('data-importer.resolver.location-strategy.staticPath') },
    { value: 'findOrCreateFolder', label: t('data-importer.resolver.location-strategy.findOrCreateFolder') },
    { value: 'findParent', label: t('data-importer.resolver.location-strategy.findParent') }
  ]

  const publishingStrategyOptions = [
    { value: 'noChangeUnpublishNew', label: t('data-importer.resolver.publishing-strategy.noChangeUnpublishNew') },
    { value: 'noChangePublishNew', label: t('data-importer.resolver.publishing-strategy.noChangePublishNew') },
    { value: 'alwaysPublish', label: t('data-importer.resolver.publishing-strategy.alwaysPublish') },
    { value: 'attributeBased', label: t('data-importer.resolver.publishing-strategy.attributeBased') }
  ]

  const findStrategyOptions = [
    { value: 'id', label: t('data-importer.resolver.location-strategy.find-strategy.id') },
    { value: 'path', label: t('data-importer.resolver.location-strategy.find-strategy.path') },
    { value: 'attribute', label: t('data-importer.resolver.location-strategy.find-strategy.attribute') }
  ]

  return (
    <FieldWidthProvider fieldWidthValues={ { medium: 600 } }>
      <>
        <StepHeading>{ t('data-importer.resolver.title') }</StepHeading>

        <DataImporterPanel>
          <Form.Item
            label={ t('data-importer.resolver.class') }
            name={ ['resolverConfig', 'dataObjectClassId'] }
            required
          >
            <Select
              filterOption={ filterByLabel }
              loadingSkeleton={ isLoadingClasses }
              options={ classOptions }
              placeholder={ t('data-importer.resolver.class-placeholder') }
              showSearch
            />
          </Form.Item>
        </DataImporterPanel>

        { /* ── Element Loading ──────────────────────────────────────────────── */ }
        <DataImporterPanel title={ t('data-importer.resolver.element-loading') }>
          <Form.Item
            label={ t('data-importer.resolver.loading-strategy') }
            name={ ['resolverConfig', 'loadingStrategy', 'type'] }
            tooltip={ t('data-importer.resolver.loading-strategy.tooltip') }
          >
            <Select
              filterOption={ filterByLabel }
              options={ loadingStrategyOptions }
              showSearch
            />
          </Form.Item>

          { /* id strategy */ }
          { loadingStrategyType === 'id' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.loading-strategy.id') }
          >
            <Form.Item
              label={ t('data-importer.resolver.loading-strategy.data-source-index') }
              name={ ['resolverConfig', 'loadingStrategy', 'settings', 'dataSourceIndex'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ columnHeaderOptions }
                placeholder={ t('data-importer.resolver.loading-strategy.data-source-index-placeholder') }
                showSearch
              />
            </Form.Item>
          </DataImporterPanel>
          ) }

          { /* path strategy */ }
          { loadingStrategyType === 'path' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.loading-strategy.path') }
          >
            <Form.Item
              label={ t('data-importer.resolver.loading-strategy.data-source-index') }
              name={ ['resolverConfig', 'loadingStrategy', 'settings', 'dataSourceIndex'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ columnHeaderOptions }
                placeholder={ t('data-importer.resolver.loading-strategy.data-source-index-placeholder') }
                showSearch
              />
            </Form.Item>
          </DataImporterPanel>
          ) }

          { /* attribute strategy */ }
          { loadingStrategyType === 'attribute' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.loading-strategy.attribute') }
          >
            <Form.Item
              label={ t('data-importer.resolver.loading-strategy.data-source-index') }
              name={ ['resolverConfig', 'loadingStrategy', 'settings', 'dataSourceIndex'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ columnHeaderOptions }
                placeholder={ t('data-importer.resolver.loading-strategy.data-source-index-placeholder') }
                showSearch
              />
            </Form.Item>
            <Form.Item
              label={ t('data-importer.resolver.loading-strategy.attribute-name') }
              name={ ['resolverConfig', 'loadingStrategy', 'settings', 'attributeName'] }
            >
              <Select
                filterOption={ filterByLabel }
                loadingSkeleton={ isLoadingLoadingAttrs }
                options={ loadingAttributeOptions }
                placeholder={ t('data-importer.resolver.loading-strategy.attribute-name-placeholder') }
                showSearch
              />
            </Form.Item>
            { loadingAttrIsLocalized && (
              <Form.Item
                label={ t('data-importer.resolver.loading-strategy.language') }
                name={ ['resolverConfig', 'loadingStrategy', 'settings', 'language'] }
              >
                <Select
                  filterOption={ filterByLabel }
                  options={ languageOptions }
                  placeholder={ t('data-importer.resolver.loading-strategy.language-placeholder') }
                  showSearch
                />
              </Form.Item>
            ) }
            <Form.Item
              name={ ['resolverConfig', 'loadingStrategy', 'settings', 'includeUnpublished'] }
              valuePropName="checked"
            >
              <Switch
                labelRight={ t('data-importer.resolver.loading-strategy.include-unpublished') }
                size="small"
              />
            </Form.Item>
          </DataImporterPanel>
          ) }
        </DataImporterPanel>

        { /* ── Element Creation ─────────────────────────────────────────────── */ }
        <DataImporterPanel title={ t('data-importer.resolver.element-creation') }>
          <Form.Item
            label={ t('data-importer.resolver.create-location-strategy') }
            name={ ['resolverConfig', 'createLocationStrategy', 'type'] }
            tooltip={ t('data-importer.resolver.create-location-strategy.tooltip') }
          >
            <Select
              filterOption={ filterByLabel }
              options={ createLocationStrategyOptions }
              showSearch
            />
          </Form.Item>

          { createLocationType === 'staticPath' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.location-strategy.staticPath') }
          >
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.path') }
              name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'path'] }
              required
              rules={ [
                { required: true, message: t('data-importer.validation.required', { field: t('data-importer.resolver.location-strategy.path') }) }
              ] }
            >
              <ManyToOneRelationPath
                allowPathTextInput
                allowedDataObjectTypes={ ['folder'] }
                dataObjectsAllowed
              />
            </Form.Item>
          </DataImporterPanel>
          ) }

          { createLocationType === 'findOrCreateFolder' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.location-strategy.findOrCreateFolder') }
          >
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.data-source-index') }
              name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'dataSourceIndex'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ columnHeaderOptions }
                placeholder={ t('data-importer.resolver.location-strategy.data-source-index-placeholder') }
                showSearch
              />
            </Form.Item>
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.fallback-path') }
              name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'fallbackPath'] }
              tooltip={ t('data-importer.resolver.location-strategy.fallback-path.tooltip') }
            >
              <Input placeholder={ t('data-importer.resolver.location-strategy.fallback-path-placeholder') } />
            </Form.Item>
          </DataImporterPanel>
          ) }

          { createLocationType === 'findParent' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.location-strategy.findParent') }
          >
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.find-strategy') }
              name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'findStrategy'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ findStrategyOptions }
                showSearch
              />
            </Form.Item>
            { createFindStrategy === 'attribute' && (
              <>
                <Form.Item
                  label={ t('data-importer.resolver.location-strategy.attribute-class') }
                  name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'attributeDataObjectClassId'] }
                >
                  <Select
                    filterOption={ filterByLabel }
                    loadingSkeleton={ isLoadingClasses }
                    options={ classOptions }
                    showSearch
                  />
                </Form.Item>
                <Form.Item
                  label={ t('data-importer.resolver.location-strategy.attribute-name') }
                  name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'attributeName'] }
                >
                  <Select
                    filterOption={ filterByLabel }
                    loadingSkeleton={ isLoadingCreateFindParentAttrs }
                    options={ createFindParentAttrOptions }
                    showSearch
                  />
                </Form.Item>
                { createFindParentAttrIsLocalized && (
                  <Form.Item
                    label={ t('data-importer.resolver.location-strategy.attribute-language') }
                    name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'attributeLanguage'] }
                  >
                    <Select
                      filterOption={ filterByLabel }
                      options={ languageOptions }
                      showSearch
                    />
                  </Form.Item>
                ) }
              </>
            ) }
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.data-source-index') }
              name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'dataSourceIndex'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ columnHeaderOptions }
                placeholder={ t('data-importer.resolver.location-strategy.data-source-index-placeholder') }
                showSearch
              />
            </Form.Item>
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.fallback-path') }
              name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'fallbackPath'] }
              tooltip={ t('data-importer.resolver.location-strategy.fallback-path.tooltip') }
            >
              <Input placeholder={ t('data-importer.resolver.location-strategy.fallback-path-placeholder') } />
            </Form.Item>
            <Form.Item
              name={ ['resolverConfig', 'createLocationStrategy', 'settings', 'asVariant'] }
              valuePropName="checked"
            >
              <Switch
                labelRight={ t('data-importer.resolver.location-strategy.as-variant') }
                size="small"
              />
            </Form.Item>
          </DataImporterPanel>
          ) }
        </DataImporterPanel>

        { /* ── Element Location Update ──────────────────────────────────────── */ }
        <DataImporterPanel title={ t('data-importer.resolver.element-location-update') }>
          <Form.Item
            label={ t('data-importer.resolver.location-update-strategy') }
            name={ ['resolverConfig', 'locationUpdateStrategy', 'type'] }
            tooltip={ t('data-importer.resolver.location-update-strategy.tooltip') }
          >
            <Select
              filterOption={ filterByLabel }
              options={ locationUpdateStrategyOptions }
              showSearch
            />
          </Form.Item>

          { updateLocationType === 'staticPath' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.location-strategy.staticPath') }
          >
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.path') }
              name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'path'] }
              required
              rules={ [
                { required: true, message: t('data-importer.validation.required', { field: t('data-importer.resolver.location-strategy.path') }) }
              ] }
            >
              <ManyToOneRelationPath
                allowPathTextInput
                allowedDataObjectTypes={ ['folder'] }
                dataObjectsAllowed
              />
            </Form.Item>
          </DataImporterPanel>
          ) }

          { updateLocationType === 'findOrCreateFolder' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.location-strategy.findOrCreateFolder') }
          >
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.data-source-index') }
              name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'dataSourceIndex'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ columnHeaderOptions }
                placeholder={ t('data-importer.resolver.location-strategy.data-source-index-placeholder') }
                showSearch
              />
            </Form.Item>
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.fallback-path') }
              name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'fallbackPath'] }
              tooltip={ t('data-importer.resolver.location-strategy.fallback-path.tooltip') }
            >
              <Input placeholder={ t('data-importer.resolver.location-strategy.fallback-path-placeholder') } />
            </Form.Item>
          </DataImporterPanel>
          ) }

          { updateLocationType === 'findParent' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.location-strategy.findParent') }
          >
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.find-strategy') }
              name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'findStrategy'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ findStrategyOptions }
                showSearch
              />
            </Form.Item>
            { updateFindStrategy === 'attribute' && (
              <>
                <Form.Item
                  label={ t('data-importer.resolver.location-strategy.attribute-class') }
                  name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeDataObjectClassId'] }
                >
                  <Select
                    filterOption={ filterByLabel }
                    options={ classOptions }
                    showSearch
                  />
                </Form.Item>
                <Form.Item
                  label={ t('data-importer.resolver.location-strategy.attribute-name') }
                  name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeName'] }
                >
                  <Select
                    filterOption={ filterByLabel }
                    options={ updateFindParentAttrOptions }
                    showSearch
                  />
                </Form.Item>
                { updateFindParentAttrIsLocalized && (
                  <Form.Item
                    label={ t('data-importer.resolver.location-strategy.attribute-language') }
                    name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeLanguage'] }
                  >
                    <Select
                      filterOption={ filterByLabel }
                      options={ languageOptions }
                      showSearch
                    />
                  </Form.Item>
                ) }
              </>
            ) }
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.data-source-index') }
              name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'dataSourceIndex'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ columnHeaderOptions }
                placeholder={ t('data-importer.resolver.location-strategy.data-source-index-placeholder') }
                showSearch
              />
            </Form.Item>
            <Form.Item
              label={ t('data-importer.resolver.location-strategy.fallback-path') }
              name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'fallbackPath'] }
              tooltip={ t('data-importer.resolver.location-strategy.fallback-path.tooltip') }
            >
              <Input placeholder={ t('data-importer.resolver.location-strategy.fallback-path-placeholder') } />
            </Form.Item>
            <Form.Item
              name={ ['resolverConfig', 'locationUpdateStrategy', 'settings', 'asVariant'] }
              valuePropName="checked"
            >
              <Switch
                labelRight={ t('data-importer.resolver.location-strategy.as-variant') }
                size="small"
              />
            </Form.Item>
          </DataImporterPanel>
          ) }
        </DataImporterPanel>

        { /* ── Element Publishing ───────────────────────────────────────────── */ }
        <DataImporterPanel title={ t('data-importer.resolver.element-publishing') }>
          <Form.Item
            label={ t('data-importer.resolver.publishing-strategy') }
            name={ ['resolverConfig', 'publishingStrategy', 'type'] }
            tooltip={ t('data-importer.resolver.publishing-strategy.tooltip') }
          >
            <Select
              filterOption={ filterByLabel }
              options={ publishingStrategyOptions }
              showSearch
            />
          </Form.Item>

          { publishingStrategyType === 'attributeBased' && (
          <DataImporterPanel
            theme="fieldset"
            title={ t('data-importer.resolver.publishing-strategy.attributeBased') }
          >
            <Form.Item
              label={ t('data-importer.resolver.publishing-strategy.data-source-index') }
              name={ ['resolverConfig', 'publishingStrategy', 'settings', 'dataSourceIndex'] }
            >
              <Select
                filterOption={ filterByLabel }
                options={ columnHeaderOptions }
                placeholder={ t('data-importer.resolver.publishing-strategy.data-source-index-placeholder') }
                showSearch
              />
            </Form.Item>
          </DataImporterPanel>
          ) }
        </DataImporterPanel>
      </>
    </FieldWidthProvider>
  )
}
