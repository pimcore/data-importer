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
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { type ClassAttribute, type MappingConfigItem } from '../../../../types'
import { PostedMappingSource } from '../mapping-source/posted-mapping-source'
import { AdvancedMappingModal } from '.'
import type * as ResultPreviewModule from './result-preview/result-preview'

const typeQuery = jest.fn()
const resultQuery = jest.fn()
const attributesQuery = jest.fn()
const refetch = jest.fn(async () => { await Promise.resolve() })

jest.mock('../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery: (...args: unknown[]) => typeQuery(...args),
  useBundleDataImporterMappingLoadTransformationResultQuery: (...args: unknown[]) => resultQuery(...args),
  useBundleDataImporterDataTypeLoadClassAttributesQuery: (...args: unknown[]) => attributesQuery(...args)
}))
jest.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))

// the pipeline editor is out of scope; the target step stand-in shows what the dialog hands it, next to the real result preview
jest.mock('./step-transformations/step-transformations', () => ({
  StepTransformations: () => null
}))
jest.mock('./step-target/step-target', () => {
  const { ResultPreview } = jest.requireActual<typeof ResultPreviewModule>('./result-preview/result-preview')
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

const renderDialog = (attributesMap: Record<string, ClassAttribute[]> = {}, onSave: (updated: MappingConfigItem) => void = () => undefined): void => {
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
        onSave={ onSave }
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
  jest.clearAllMocks()
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

  describe('when the result endpoint fails', () => {
    // Studio's API error body; in dev, detail carries the stack trace
    const trace = '#0 /var/www/src/Service/Studio/TransformationService.php(150): ...'
    const failedWith = (data: unknown, status = 500): Record<string, unknown> => ({ data: undefined, error: { status, data }, isLoading: false, isFetching: false, isError: true, isSuccess: false, refetch })
    const onFirstRecord = (request: { bundleDataImporterTransformationResultParameters: { dataRow: unknown } }): boolean =>
      request.bundleDataImporterTransformationResultParameters.dataRow === records[0]

    it('shows the error the backend reports and stays usable', () => {
      const refused = failedWith({ message: 'The operator "importAsset" writes or fetches data, so a posted mapping cannot preview it.', errorKey: 'error_environment', detail: trace })
      resultQuery.mockImplementation((request: Parameters<typeof onFirstRecord>[0], options: { skip: boolean }) => {
        if (options.skip) return idle
        return onFirstRecord(request) ? refused : previewResult
      })
      const onSave = jest.fn()
      renderDialog({}, onSave)

      const target = within(screen.getByTestId('target'))
      expect(target.getByText('The operator "importAsset" writes or fetches data, so a posted mapping cannot preview it.')).toBeTruthy()
      expect(target.queryByText('data-importer.mapping.advanced-modal.no-preview')).toBeNull()

      fireEvent.click(target.getByRole('button', { name: 'chevron-right' }))
      expect(resultQuery).toHaveBeenLastCalledWith(
        { bundleDataImporterTransformationResultParameters: { mappingConfig: [item], dataRow: records[1] } },
        { refetchOnMountOrArgChange: false, skip: false }
      )
      expect(target.getByText('trimmed A-1')).toBeTruthy()

      fireEvent.click(screen.getByRole('button', { name: 'data-importer.mapping.advanced-modal.save' }))
      expect(onSave).toHaveBeenCalledWith(expect.objectContaining({ id: 'm1', dataSourceIndex: ['sku'] }))
    })

    it('shows the message of a refused request', () => {
      resultQuery.mockImplementation((_request: unknown, options: { skip: boolean }) => options.skip ? idle : failedWith({ message: 'Access denied to the data importer', errorKey: 'error_something_generic_went_wrong', detail: trace }, 403))
      renderDialog()

      const target = within(screen.getByTestId('target'))
      expect(target.getByText('Access denied to the data importer')).toBeTruthy()
      expect(target.queryByText(trace)).toBeNull()
    })

    it('shows a general error when the response has no message', () => {
      resultQuery.mockImplementation((_request: unknown, options: { skip: boolean }) => options.skip ? idle : failedWith('<html>Internal Server Error</html>'))
      renderDialog()

      const target = within(screen.getByTestId('target'))
      expect(target.getByText('data-importer.mapping.advanced-modal.preview-error')).toBeTruthy()
      expect(target.queryByText('data-importer.mapping.advanced-modal.no-preview')).toBeNull()
    })
  })
})
