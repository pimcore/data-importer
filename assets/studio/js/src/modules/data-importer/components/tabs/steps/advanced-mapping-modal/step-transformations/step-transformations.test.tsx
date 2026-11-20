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
import { type TransformationPipelineItem } from '../../../../../types'
import { ConfigEditorModeProvider } from '../../../../config-editor-mode'
import { StepTransformations } from './step-transformations'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
jest.mock('@pimcore/studio-ui-bundle', () => ({
  container: {
    get: () => ({
      getDynamicTypes: () => [],
      getDynamicType: () => ({ label: 'Trim', renderSettings: (settings: { mode: string }) => <output>{ settings.mode }</output> })
    })
  }
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  ...jest.requireActual('../../../../../../../../test/support/studio-components'),
  Dropdown: ({ children }: { children?: React.ReactNode }) => <>{ children }</>,
  IconTextButton: ({ children }: { children?: React.ReactNode }) => <button type="button">{ children }</button>,
  CollapseItem: ({ children, extra, label }: { children?: React.ReactNode, extra?: React.ReactNode, label?: React.ReactNode }) => (
    <section>{ label }{ extra }{ children }</section>
  )
}))
jest.mock('../result-preview/result-preview', () => ({ ResultPreview: () => null }))

const pipeline = [{ type: 'trim', settings: { mode: 'both' } }] as unknown as TransformationPipelineItem[]

const renderStep = (readOnly: boolean): void => {
  render(
    <ConfigEditorModeProvider readOnly={ readOnly }>
      <StepTransformations
        columnHeaderOptions={ [{ value: 'sku', label: 'SKU' }] }
        dataSourceIndex={ ['sku'] }
        onDataSourceIndexChange={ () => undefined }
        onPipelineChange={ () => undefined }
        pipeline={ pipeline }
      />
    </ConfigEditorModeProvider>
  )
}

afterEach(cleanup)

describe('StepTransformations', () => {
  it('shows a reader the pipeline and its settings without add, remove, reorder or source edit', () => {
    renderStep(true)

    expect(screen.getByText('Trim')).toBeTruthy()
    expect(screen.getByText('both')).toBeTruthy()
    expect(screen.getByText('SKU')).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'data-importer.mapping.add' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'trash' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'edit-pen' })).toBeNull()
    expect(screen.queryByText('⠿')).toBeNull()
  })

  it('offers an editor every pipeline action', () => {
    renderStep(false)

    expect(screen.getByRole('button', { name: 'data-importer.mapping.add' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'trash' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'edit-pen' })).toBeTruthy()
    expect(screen.getByText('⠿')).toBeTruthy()
  })
})
