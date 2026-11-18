/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

// The mapping step's Preview rows under test: the real step and rows on a posted record or a stored
// configuration, a stand-in mapping list and a small RTK Query stand-in for the transformation result
import React, { useEffect, useReducer, useRef, useState } from 'react'
import { Form } from 'antd'
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react'
import * as gen from '../../src/modules/data-importer/data-importer-api-slice.gen'
import { PostedMappingSource } from '../../src/modules/data-importer/components/tabs/steps/mapping-source/posted-mapping-source'
import { type MappingSource, MappingSourceContext, type MappingSourceQuery } from '../../src/modules/data-importer/components/tabs/steps/mapping-source/mapping-source'
import { storedConfigQueries } from '../../src/modules/data-importer/components/tabs/steps/mapping-source/stored-config-mapping-source'
import { MappingStep } from '../../src/modules/data-importer/components/tabs/steps/mapping-step'
import { PREVIEW_REQUEST_DEBOUNCE_MS, useMappingPreviewMode } from '../../src/modules/data-importer/components/tabs/steps/mapping-step/preview-mode/mapping-preview-mode'
import { PreviewModeLine } from '../../src/modules/data-importer/components/tabs/steps/mapping-step/preview-mode/preview-mode-line'
import { MappingItemWithFilter } from '../../src/modules/data-importer/components/tabs/steps/mapping-step/mapping-item/mapping-item-with-filter'

// the step's mapping list without its toolbar: the real rows, the second one expanded, a filter and an edit
export const MappingList = (): React.JSX.Element => {
  const form = Form.useFormInstance()
  const [activeFilter, setActiveFilter] = useState<string | null>(null)
  const { setRecordNumber } = useMappingPreviewMode()

  return (
    <>
      <PreviewModeLine
        allCollapsed={ false }
        onCollapseAll={ () => undefined }
        showCollapseAll={ false }
      />
      <button
        onClick={ () => { setActiveFilter('sku') } }
        type="button"
      >
        filter by sku
      </button>
      <button
        onClick={ () => { form.setFieldValue(['mappingConfig', 0, 'label'], 'Name, edited') } }
        type="button"
      >
        edit first mapping
      </button>
      <button
        onClick={ () => { setRecordNumber(1) } }
        type="button"
      >
        select second record
      </button>
      { /* the list registers the mappings, as the real panel's does */ }
      <Form.List name="mappingConfig">
        { (fields) => (
          <ol>
            { fields.map(({ key, name: index }) => (
              <li
                data-testid={ `mapping-${index}` }
                key={ key }
              >
                <MappingItemWithFilter
                  activeFilter={ activeFilter }
                  add={ () => undefined }
                  expanded={ index === 1 }
                  fieldIndex={ index }
                  insertIndex={ index }
                  isNew={ false }
                  mappingId={ `m${index}` }
                  onDropped={ () => undefined }
                  onInsertItem={ () => undefined }
                  onRemoveItem={ () => undefined }
                  onToggle={ () => undefined }
                  remove={ () => undefined }
                />
              </li>
            )) }
          </ol>
        ) }
      </Form.List>
    </>
  )
}

// a small RTK Query stand-in: one request per distinct argument, shared by every row asking with
// it, answered by the test; while a newer one runs the older response stays in `data`, as in RTK
type Entry = { status: 'pending' } | { status: 'done', data: { transformationResultPreviews: string[] } } | { status: 'failed', error: unknown }
const entries = new Map<string, Entry>()
export const sent: unknown[] = []
const listeners = new Set<() => void>()
const notify = (): void => { listeners.forEach(listener => { listener() }) }

const useTransformationResult = (arg: unknown, options?: { skip?: boolean }): MappingSourceQuery<{ transformationResultPreviews: string[] }> => {
  const [, rerender] = useReducer((count: number) => count + 1, 0)
  const lastData = useRef<{ transformationResultPreviews: string[] } | undefined>(undefined)
  const key = options?.skip === true ? null : JSON.stringify(arg)

  useEffect(() => {
    listeners.add(rerender)
    return () => { listeners.delete(rerender) }
  }, [])

  // a failed request is sent again when a row subscribes to it anew, as RTK Query does
  useEffect(() => {
    if (key === null || (entries.has(key) && entries.get(key)?.status !== 'failed')) return
    entries.set(key, { status: 'pending' })
    sent.push(arg)
    notify()
  }, [key])

  const entry = key === null ? undefined : entries.get(key)
  if (key === null) lastData.current = undefined
  if (entry?.status === 'done') lastData.current = entry.data

  return {
    data: lastData.current,
    isLoading: lastData.current === undefined && entry?.status !== 'failed' && key !== null,
    isFetching: key !== null && (entry === undefined || entry.status === 'pending'),
    isError: entry?.status === 'failed',
    isSuccess: entry?.status === 'done',
    error: entry?.status === 'failed' ? entry.error : undefined,
    refetch: async () => undefined
  }
}

export const respond = (request: number, previews: string[]): void => {
  act(() => {
    entries.set(JSON.stringify(sent[request]), { status: 'done', data: { transformationResultPreviews: previews } })
    notify()
  })
}

export const fail = (request: number, error: unknown): void => {
  act(() => {
    entries.set(JSON.stringify(sent[request]), { status: 'failed', error })
    notify()
  })
}

const settled = <T, >(data: T | undefined): MappingSourceQuery<T> => ({
  data,
  isLoading: false,
  isFetching: false,
  isError: false,
  isSuccess: data !== undefined,
  refetch: async () => undefined
})

export const columns = [{ dataIndex: 'sku', label: 'SKU' }, { dataIndex: 'name', label: 'Name' }]
export const records = [{ sku: 'A-1', name: 'Stuhl' }, { sku: 'A-2', name: 'Tisch' }]
export const mappingConfig = [
  { label: 'Name', dataSourceIndex: ['name'], transformationResultType: 'input', dataTarget: { type: 'direct', settings: { fieldName: 'name' } } },
  { label: 'SKU', dataSourceIndex: ['sku'], transformationResultType: 'input', dataTarget: { type: 'direct', settings: { fieldName: 'sku' } } },
  { label: 'Combined', dataSourceIndex: ['sku', 'name'] },
  { label: 'Loose', dataSourceIndex: ['sku'] }
]
const configuration = { resolverConfig: { dataObjectClassId: 'Product' }, mappingConfig }

export const PostedHost = ({ mappings = mappingConfig }: { mappings?: Array<Record<string, unknown>> }): React.JSX.Element => {
  const [form] = Form.useForm()

  return (
    <Form
      form={ form }
      initialValues={ { mappingConfig: mappings } }
    >
      <PostedMappingSource
        columns={ columns }
        configuration={ { ...configuration, mappingConfig: mappings } }
        id="posted"
        records={ records }
      >
        <MappingStep isActive />
      </PostedMappingSource>
    </Form>
  )
}

// a stored configuration with an uploaded preview file, read through its endpoints
const storedColumnHeaders = { columnHeaders: columns.map(column => ({ id: column.dataIndex, ...column })) }
const storedPreviews = records.map((record, index) => ({
  previewRecordIndex: index,
  dataPreview: columns.map(column => ({ dataIndex: column.dataIndex, label: column.label, data: record[column.dataIndex as 'sku' | 'name'] }))
}))
const storedSource: MappingSource = {
  id: 'qa-mapping-preview',
  configuration,
  isConfigurationLoaded: true,
  revision: '1',
  ...storedConfigQueries('qa-mapping-preview')
}

export const StoredHost = (): React.JSX.Element => {
  const [form] = Form.useForm()

  return (
    <Form
      form={ form }
      initialValues={ { mappingConfig } }
    >
      <MappingSourceContext.Provider value={ storedSource }>
        <MappingStep isActive />
      </MappingSourceContext.Provider>
    </Form>
  )
}

export const loaded = async (): Promise<void> => {
  await waitFor(() => { expect(screen.getByTestId('content').getAttribute('aria-busy')).toBe('false') })
}

export const afterDebounce = async (): Promise<void> => {
  await act(async () => { await new Promise(resolve => setTimeout(resolve, PREVIEW_REQUEST_DEBOUNCE_MS + 100)) })
}

export const switchPreviewOn = async (): Promise<void> => {
  fireEvent.click(screen.getByRole('switch', { name: 'data-importer.mapping.preview-mode.switch' }))
  await afterDebounce()
}

export const previewRowOf = (index: number): HTMLElement =>
  within(screen.getByTestId(`mapping-${index}`)).getByRole('group', { name: 'data-importer.mapping.preview-mode.preview' })

export const previewTexts = (): string[] => [0, 1, 2, 3].map(index => previewRowOf(index).textContent ?? '')

// every request starts unanswered; the stored configuration's other endpoints answer at once
export const mockTransformationQueries = (): void => {
  entries.clear()
  sent.length = 0
  jest.mocked(gen.useBundleDataImporterMappingLoadTransformationResultQuery).mockImplementation(useTransformationResult as never)
  jest.mocked(gen.useBundleDataImporterConfigLoadTransformationResultQuery).mockImplementation(useTransformationResult as never)
  jest.mocked(gen.useBundleDataImporterMappingCalculateTransformationResultTypeQuery).mockImplementation((() => settled(undefined)) as never)
  jest.mocked(gen.useBundleDataImporterConfigCalculateTransformationResultTypeQuery).mockImplementation((() => settled(undefined)) as never)
  jest.mocked(gen.useBundleDataImporterConfigLoadColumnHeadersQuery).mockImplementation(((_: unknown, options?: { skip?: boolean }) =>
    settled(options?.skip === true ? undefined : storedColumnHeaders)) as never)
  jest.mocked(gen.useBundleDataImporterConfigLoadPreviewQuery).mockImplementation(((arg: { bundleDataImporterLoadPreviewParameters: { recordNumber?: number } }, options?: { skip?: boolean }) =>
    settled(options?.skip === true ? undefined : storedPreviews[arg.bundleDataImporterLoadPreviewParameters.recordNumber ?? 0])) as never)
}
