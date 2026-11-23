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
import { cleanup, render, screen } from '@testing-library/react'
import { PreviewModeLine } from './preview-mode-line'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useTranslation: () => ({
    t: (key: string, options?: Record<string, unknown>) => options === undefined ? key : `${key}(${Object.values(options).join(',')})`
  })
}))
jest.mock('../../mapping-source/mapping-source', () => ({ useMappingSource: () => ({ configName: undefined }) }))
jest.mock('../../shared/use-preview-data-actions', () => ({ usePreviewDataActions: () => ({}) }))
// preview mode on, record 2 of 3 selected
jest.mock('./mapping-preview-mode', () => ({
  useMappingPreviewMode: () => ({
    enabled: true,
    setEnabled: () => undefined,
    noPreviewData: false,
    loadError: null,
    previewError: null,
    record: [{ dataIndex: 'sku', value: 'A-2' }],
    recordNumber: 1,
    setRecordNumber: () => undefined,
    recordCount: 3
  })
}))

afterEach(cleanup)

describe('Preview mode line without Preview item (platform-version#858)', () => {
  it('AC1: preview mode on shows the selected record with its arrows and no Preview item button', () => {
    render(
      <PreviewModeLine
        allCollapsed={ false }
        onCollapseAll={ () => undefined }
        showCollapseAll
      />
    )

    expect(screen.getByRole<HTMLInputElement>('textbox', { name: 'data-importer.mapping.preview-mode.record-label' }).value).toBe('2')
    expect(screen.getByText('A-2', { selector: '[data-record-value]' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'chevron-left' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'chevron-right' })).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'data-importer.mapping.preview-mode.preview-item' })).toBeNull()
    expect(screen.queryByText('data-importer.mapping.preview-mode.preview-item')).toBeNull()
  })
})
