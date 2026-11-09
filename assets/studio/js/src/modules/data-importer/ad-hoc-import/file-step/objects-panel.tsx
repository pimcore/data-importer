/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useMemo } from 'react'
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { api as classDefinitionApi } from '@pimcore/studio-ui-bundle/api/class-definition'
import { Flex, Form, FormKit, ManyToOneRelationPath, Segmented, Select } from '@pimcore/studio-ui-bundle/components'
import { useBundleDataImporterAdHocImportLoadMatchFieldsQuery } from '../../data-importer-api-slice-enhanced'
import { parseClassAttribute } from '../../components/tabs/steps/mapping-step/hooks/use-mapping-step-loader.types'
import { filterByLabel } from '../../utils/select-utils'
import { type InspectedFile, type MatchBy, type ObjectsSettings } from './file-step-state'

const CLASS_WIDTH = 200
const MATCH_WIDTH = 140
const FOLDER_WIDTH = 300
const FORM_ITEM_STYLE = { marginBottom: 0 }

const useStyles = createStyles(({ token, css }) => ({
  quiet: css`
    color: ${token.colorTextTertiary};
  `
}))

export interface ObjectsPanelProps {
  inspected?: InspectedFile
  objects: ObjectsSettings
  onChange: (objects: ObjectsSettings) => void
}

export const ObjectsPanel = ({ inspected, objects, onChange }: ObjectsPanelProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { classId, matchBy, matchField, matchColumn, newObjectsFolder } = objects

  const { data: classes, isLoading: isLoadingClasses } = classDefinitionApi.endpoints.classDefinitionCollectionCreatable.useQuery({})
  const classOptions = useMemo(
    () => (classes?.items ?? []).map(({ id, name }) => ({ value: id, label: name })),
    [classes]
  )

  // with one class there is nothing to choose
  useEffect(() => {
    if (classId === undefined && classOptions.length === 1) {
      onChange({ ...objects, classId: classOptions[0].value })
    }
  }, [classId, classOptions])

  // not `data`: it still holds the previous class's fields while the next ones load
  const { currentData: fields, isFetching: isLoadingFields } = useBundleDataImporterAdHocImportLoadMatchFieldsQuery(
    { classId: classId ?? '' },
    { skip: classId === undefined }
  )
  // no language to match a localized field in
  const fieldOptions = useMemo(
    () => (fields?.attributes ?? [])
      .map(parseClassAttribute)
      .filter(({ localized }) => !localized)
      .map(({ key, title }) => ({ value: key, label: title })),
    [fields]
  )

  const columnOptions = useMemo(
    () => (inspected?.columns ?? []).map(({ dataIndex, label }) => ({ value: dataIndex, label })),
    [inspected]
  )
  const hasColumns = columnOptions.length > 0

  // without matching, the column only names the new objects
  const createsOnly = matchBy === 'none'
  const columnLabel = createsOnly ? 'data-importer.ad-hoc-import.objects.key-from-column' : 'data-importer.ad-hoc-import.objects.from-column'

  return (
    <FormKit.Panel
      title={ t('data-importer.ad-hoc-import.objects.title') }
    >
      <Form.Item
        htmlFor="ad-hoc-import-class"
        label={ t('data-importer.ad-hoc-import.objects.class') }
        style={ FORM_ITEM_STYLE }
      >
        <Select
          filterOption={ filterByLabel }
          id="ad-hoc-import-class"
          loadingSkeleton={ isLoadingClasses }
          onChange={ (next: string) => { onChange({ ...objects, classId: next, matchField: undefined }) } }
          options={ classOptions }
          showSearch
          style={ { width: CLASS_WIDTH } }
          value={ classId }
        />
      </Form.Item>

      <Form.Item
        extra={ t(createsOnly ? 'data-importer.ad-hoc-import.objects.create-only-hint' : 'data-importer.ad-hoc-import.objects.match-hint') }
        label={ t('data-importer.ad-hoc-import.objects.match-by') }
        style={ FORM_ITEM_STYLE }
      >
        <Flex
          align="center"
          gap="small"
        >
          <Segmented
            onChange={ (next) => { onChange({ ...objects, matchBy: next as MatchBy }) } }
            options={ [
              { value: 'id', label: t('data-importer.ad-hoc-import.objects.match-by.object-id') },
              { value: 'field', label: t('data-importer.ad-hoc-import.objects.match-by.field') },
              { value: 'none', label: t('data-importer.ad-hoc-import.objects.match-by.none') }
            ] }
            value={ matchBy }
          />
          { matchBy === 'field' && (
            <Select
              aria-label={ t('data-importer.ad-hoc-import.objects.match-by.field') }
              filterOption={ filterByLabel }
              loadingSkeleton={ isLoadingFields }
              onChange={ (next: string) => { onChange({ ...objects, matchField: next }) } }
              options={ fieldOptions }
              showSearch
              style={ { width: MATCH_WIDTH } }
              value={ matchField }
            />
          ) }
          <span className={ styles.quiet }>{ t(columnLabel) }</span>
          <Select
            aria-label={ t(columnLabel) }
            disabled={ !hasColumns }
            filterOption={ filterByLabel }
            onChange={ (next: string) => { onChange({ ...objects, matchColumn: next }) } }
            options={ columnOptions }
            placeholder={ hasColumns ? undefined : t('data-importer.ad-hoc-import.objects.after-the-file') }
            showSearch
            style={ { width: MATCH_WIDTH } }
            value={ matchColumn }
          />
        </Flex>
      </Form.Item>

      <Form.Item
        extra={ t('data-importer.ad-hoc-import.objects.new-objects-hint') }
        label={ (
          <span>
            { t('data-importer.ad-hoc-import.objects.new-objects-in') }
            { ' ' }
            <span className={ styles.quiet }>{ t('data-importer.ad-hoc-import.objects.optional') }</span>
          </span>
        ) }
        style={ FORM_ITEM_STYLE }
      >
        <ManyToOneRelationPath
          allowedDataObjectTypes={ ['folder'] }
          dataObjectsAllowed
          onChange={ (next) => { onChange({ ...objects, newObjectsFolder: next }) } }
          value={ newObjectsFolder }
          width={ FOLDER_WIDTH }
        />
      </Form.Item>
    </FormKit.Panel>
  )
}
