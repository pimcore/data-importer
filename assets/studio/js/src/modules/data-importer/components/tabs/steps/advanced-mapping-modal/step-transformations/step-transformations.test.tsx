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
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { type TransformationPipelineItem } from '../../../../../types'
import { ConfigEditorModeProvider } from '../../../../config-editor-mode'
import { StepTransformations } from './step-transformations'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
jest.mock('@pimcore/studio-ui-bundle', () => ({
  container: {
    get: () => ({
      getDynamicTypes: () => [{ id: 'trim', label: 'Trim', group: 'dataManipulation' }],
      getDynamicType: () => ({ label: 'Trim', renderSettings: (settings: { mode: string }) => <output>{ settings.mode }</output> })
    })
  }
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  ...jest.requireActual('../../../../../../../../test/support/studio-components'),
  // the menu's leaves as buttons next to the trigger
  Dropdown: ({ children, menu }: { children?: React.ReactNode, menu: { items: Array<{ children: Array<{ key: string, label: string }> }>, onClick: (info: { key: string }) => void } }) => (
    <>
      { children }
      { menu.items.flatMap(group => group.children).map(leaf => (
        <button
          key={ leaf.key }
          onClick={ () => { menu.onClick({ key: leaf.key }) } }
          type="button"
        >
          { `add ${leaf.label}` }
        </button>
      )) }
    </>
  ),
  IconTextButton: ({ children }: { children?: React.ReactNode }) => <button type="button">{ children }</button>,
  CollapseItem: ({ children, extra, label }: { children?: React.ReactNode, extra?: React.ReactNode, label?: React.ReactNode }) => (
    <section>{ label }{ extra }{ children }</section>
  )
}))
jest.mock('../result-preview/result-preview', () => ({ ResultPreview: () => null }))

const pipeline = [{ type: 'trim', settings: { mode: 'both' } }] as unknown as TransformationPipelineItem[]

const renderStep = (readOnly: boolean, onPipelineChange: (pipeline: TransformationPipelineItem[]) => void = () => undefined): void => {
  render(
    <ConfigEditorModeProvider readOnly={ readOnly }>
      <StepTransformations
        columnHeaderOptions={ [{ value: 'sku', label: 'SKU' }] }
        dataSourceIndex={ ['sku'] }
        onDataSourceIndexChange={ () => undefined }
        onPipelineChange={ onPipelineChange }
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

  it('appends the transformer an editor picks to the pipeline', () => {
    const onPipelineChange = jest.fn()
    renderStep(false, onPipelineChange)

    fireEvent.click(screen.getByRole('button', { name: 'add Trim' }))

    expect(onPipelineChange).toHaveBeenCalledWith([...pipeline, { type: 'trim', settings: {} }])
  })
})
