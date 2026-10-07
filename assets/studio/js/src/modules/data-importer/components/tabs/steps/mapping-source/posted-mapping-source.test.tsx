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
import { cleanup, render } from '@testing-library/react'
import { PostedMappingSource } from './posted-mapping-source'
import { type MappingSource, useMappingSource } from './mapping-source'

const typeQuery = jest.fn()
const resultQuery = jest.fn()

jest.mock('../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterMappingCalculateTransformationResultTypeQuery: (...args: unknown[]) => typeQuery(...args),
  useBundleDataImporterMappingLoadTransformationResultQuery: (...args: unknown[]) => resultQuery(...args)
}))

afterEach(() => {
  cleanup()
  typeQuery.mockReset()
  resultQuery.mockReset()
  jest.restoreAllMocks()
})

const columns = [{ dataIndex: 'sku', label: 'SKU' }, { dataIndex: 'name [de]' }]
const records = [{ sku: 'A-1', 'name [de]': 'Stuhl' }, { sku: 'A-2' }]
const configuration = { mappingConfig: [{ label: 'stored' }] }

const renderWith = (consume: (source: MappingSource) => void): void => {
  const Consumer = (): null => {
    consume(useMappingSource())
    return null
  }

  render(
    <PostedMappingSource
      columns={ columns }
      configuration={ configuration }
      id="format"
      records={ records }
    >
      <Consumer />
    </PostedMappingSource>
  )
}

describe('PostedMappingSource', () => {
  it('serves the given columns as column headers', () => {
    renderWith((source) => {
      expect(source.useColumnHeadersQuery({}).data).toEqual({
        columnHeaders: [
          { id: 'sku', dataIndex: 'sku', label: 'SKU' },
          { id: 'name [de]', dataIndex: 'name [de]', label: 'name [de]' }
        ]
      })
      expect(source.useColumnHeadersQuery(undefined).data).toBeUndefined()
    })
  })

  it('previews the requested record and stays within the records', () => {
    renderWith((source) => {
      expect(source.usePreviewQuery({ recordNumber: 1 }).data).toEqual({
        previewRecordIndex: 1,
        dataPreview: [
          { dataIndex: 'sku', label: 'SKU', data: 'A-2' },
          { dataIndex: 'name [de]', label: 'name [de]', data: '' }
        ]
      })
      expect(source.usePreviewQuery({ recordNumber: 7 }).data?.previewRecordIndex).toBe(1)
      expect(source.usePreviewQuery({ recordNumber: 0 }, { skip: true }).isSuccess).toBe(false)
    })
  })

  it('settles its previews at once, so a refetch has nothing to request', async () => {
    let refetch: (() => Promise<unknown>) | undefined
    renderWith((source) => {
      refetch = source.usePreviewQuery({ recordNumber: 0 }).refetch
    })

    await expect(refetch?.()).resolves.toBeUndefined()
  })

  it('is required around the mapping step', () => {
    const Consumer = (): null => {
      useMappingSource()
      return null
    }
    // React and jsdom report the render error on their own; it is the expected outcome here
    jest.spyOn(console, 'error').mockImplementation(() => undefined)
    const silence = (event: ErrorEvent): void => { event.preventDefault() }
    window.addEventListener('error', silence)

    try {
      expect(() => render(<Consumer />)).toThrow('The mapping step needs a mapping source around it')
    } finally {
      window.removeEventListener('error', silence)
    }
  })

  it('previews edited records', () => {
    const seen: unknown[] = []
    const Consumer = (): null => {
      seen.push(useMappingSource().usePreviewQuery({ recordNumber: 0 }).data?.dataPreview[0].data)
      return null
    }
    const view = (rows: Array<Record<string, unknown>>): React.JSX.Element => (
      <PostedMappingSource
        columns={ columns }
        configuration={ configuration }
        id="format"
        records={ rows }
      >
        <Consumer />
      </PostedMappingSource>
    )

    const { rerender } = render(view(records))
    rerender(view([{ sku: 'B-7' }]))

    expect(seen[seen.length - 1]).toBe('B-7')
  })

  it('posts the mapping and the record to the transformation result endpoint', () => {
    resultQuery.mockReturnValue({ isSuccess: true })
    renderWith((source) => {
      source.useTransformationResultQuery({ recordNumber: 0, currentConfig: { mappingConfig: [{ label: 'edited' }] } })
      source.useTransformationResultQuery({ recordNumber: 1 })
      source.useTransformationResultQuery(undefined)
    })

    expect(resultQuery.mock.calls[0]).toEqual([
      { bundleDataImporterTransformationResultParameters: { mappingConfig: [{ label: 'edited' }], dataRow: records[0] } },
      { skip: false }
    ])
    expect(resultQuery.mock.calls[1][0]).toEqual(
      { bundleDataImporterTransformationResultParameters: { mappingConfig: configuration.mappingConfig, dataRow: records[1] } }
    )
    expect(resultQuery.mock.calls[2][1]).toEqual({ skip: true })
  })

  it('posts the mapping entry to the transformation result type endpoint', () => {
    typeQuery.mockReturnValue({ isSuccess: true })
    renderWith((source) => {
      source.useTransformationResultTypeQuery({ currentConfig: { dataSourceIndex: ['sku'] } }, { refetchOnMountOrArgChange: false })
    })

    expect(typeQuery).toHaveBeenCalledWith(
      { bundleDataImporterCalculateTransformationResultTypeParameters: { currentConfig: { dataSourceIndex: ['sku'] } } },
      { refetchOnMountOrArgChange: false, skip: false }
    )
  })
})
