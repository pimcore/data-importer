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
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { type ClassAttribute, type MappingConfigItem } from '../src/modules/data-importer/types'

const typeQuery = vi.fn()
const resultQuery = vi.fn()
const attributesQuery = vi.fn()
const refetch = vi.fn(async () => { await Promise.resolve() })

vi.mock('../src/modules/data-importer/data-importer-api-slice.gen', () => ({
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery: (...args: unknown[]) => typeQuery(...args),
  useBundleDataImporterMappingLoadTransformationResultQuery: (...args: unknown[]) => resultQuery(...args),
  useBundleDataImporterDataTypeLoadClassAttributesQuery: (...args: unknown[]) => attributesQuery(...args)
}))
vi.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
vi.mock('@pimcore/studio-ui-bundle/components', async () => await import('./support/studio-components'))

// the pipeline editor is out of scope; the target step stand-in shows what the dialog hands it, next to the real result preview
vi.mock('../src/modules/data-importer/components/tabs/steps/advanced-mapping-modal/step-transformations/step-transformations', () => ({
  StepTransformations: () => null
}))
vi.mock('../src/modules/data-importer/components/tabs/steps/advanced-mapping-modal/step-target/step-target', async () => {
  const { ResultPreview } = await import('../src/modules/data-importer/components/tabs/steps/advanced-mapping-modal/result-preview/result-preview')
  return {
    StepTarget: ({ attributesMap, transformationResultType }: { attributesMap: Record<string, ClassAttribute[]>, transformationResultType?: string }) => (
      <div data-testid="target">
        <span data-testid="target-attributes">
          { Object.entries(attributesMap).map(([key, attributes]) => `${key}:${attributes.map(a => a.key).join(',')}`).join(';') }
        </span>
        <span data-testid="target-type">{ transformationResultType ?? '' }</span>
        <ResultPreview />
      </div>
    )
  }
})

const { PostedMappingSource } = await import('../src/modules/data-importer/components/tabs/steps/mapping-source/posted-mapping-source')
const { AdvancedMappingModal } = await import('../src/modules/data-importer/components/tabs/steps/advanced-mapping-modal')

const columns = [{ dataIndex: 'sku', label: 'SKU' }, { dataIndex: 'name' }]
const records = [{ sku: 'A-1', name: 'Chair' }, { sku: 'A-2', name: 'Table' }]
const item = {
  id: 'm1',
  label: 'SKU',
  dataSourceIndex: ['sku'],
  transformationPipeline: [{ type: 'trim', settings: { mode: 'both' } }]
} as unknown as MappingConfigItem

// RTK Query hands out the same result while its request is unchanged; effects in the dialog rely on that
const settled = (data?: unknown): Record<string, unknown> => ({ data, isLoading: false, isFetching: false, isError: false, isSuccess: data !== undefined, refetch })
const idle = settled()
const typeResult = settled({ type: 'numeric' })
const previewResult = settled({ transformationResultPreviews: ['trimmed A-1'] })
const attributesResult = settled({ attributes: [{ key: 'sku', title: 'SKU' }] })

const renderDialog = (attributesMap: Record<string, ClassAttribute[]> = {}): void => {
  render(
    <PostedMappingSource
      columns={ columns }
      configuration={ { mappingConfig: [item] } }
      id="format"
      records={ records }
    >
      <AdvancedMappingModal
        attributesMap={ attributesMap }
        classId="Product"
        columnHeaderOptions={ [{ value: 'sku', label: 'SKU' }] }
        item={ item }
        onClose={ () => undefined }
        onSave={ () => undefined }
        open
      />
    </PostedMappingSource>
  )
}

beforeEach(() => {
  typeQuery.mockImplementation((_request: unknown, options: { skip: boolean }) => options.skip ? idle : typeResult)
  resultQuery.mockImplementation((_request: unknown, options: { skip: boolean }) => options.skip ? idle : previewResult)
  attributesQuery.mockImplementation((_request: unknown, options: { skip: boolean }) => options.skip ? idle : attributesResult)
})

afterEach(() => {
  cleanup()
  vi.clearAllMocks()
})

describe('AdvancedMappingModal on a posted mapping source', () => {
  it('previews the posted record and the transformation result of the edited entry', () => {
    renderDialog()

    expect(screen.getByText('A-1')).toBeTruthy()
    expect(screen.getByText('Chair')).toBeTruthy()
    expect(within(screen.getByTestId('target')).getByText('trimmed A-1')).toBeTruthy()
    expect(resultQuery).toHaveBeenCalledWith(
      { bundleDataImporterTransformationResultParameters: { mappingConfig: [item], dataRow: records[0] } },
      { refetchOnMountOrArgChange: false, skip: false }
    )
  })

  it('loads the default class attributes when its host has none', () => {
    renderDialog()

    expect(attributesQuery).toHaveBeenLastCalledWith(
      { classId: 'Product', transformationResultType: undefined, systemWrite: true },
      { skip: false }
    )
    expect(screen.getByTestId('target-attributes').textContent).toBe('__default__:sku')
  })

  it('uses the host\'s attributes when it has them', () => {
    renderDialog({ __default__: [{ key: 'name', title: 'Name', localized: false }] })

    expect(attributesQuery).toHaveBeenLastCalledWith(expect.anything(), { skip: true })
    expect(screen.getByTestId('target-attributes').textContent).toBe('__default__:name')
  })

  it('posts the edited entry to the type endpoint on refresh and adopts the type', () => {
    renderDialog()
    expect(screen.getByTestId('target-type').textContent).toBe('')

    fireEvent.click(screen.getByRole('button', { name: 'refresh' }))

    expect(typeQuery).toHaveBeenCalledWith(
      {
        bundleDataImporterCalculateTransformationResultTypeParameters: {
          currentConfig: { label: 'SKU', dataSourceIndex: ['sku'], transformationPipeline: item.transformationPipeline }
        }
      },
      { refetchOnMountOrArgChange: false, skip: false }
    )
    expect(screen.getByTestId('target-type').textContent).toBe('numeric')
  })
})
