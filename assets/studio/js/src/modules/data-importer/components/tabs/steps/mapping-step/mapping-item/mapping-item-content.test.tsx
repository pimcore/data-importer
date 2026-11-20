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
import { Form } from 'antd'
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { ConfigEditorModeProvider } from '../../../../config-editor-mode'
import { MappingItemContent } from './mapping-item-content'

// antd's own controls, so a disabled form disables them as it does in Studio
jest.mock('@pimcore/studio-ui-bundle/components', () => {
  const antd = jest.requireActual('antd')
  return {
    Form: antd.Form,
    Input: antd.Input,
    Select: () => null,
    IconTextButton: ({ children, disabled, onClick }: { children?: React.ReactNode, disabled?: boolean, onClick?: () => void }) => (
      <antd.Button
        disabled={ disabled }
        onClick={ onClick }
      >{ children }</antd.Button>
    ),
    IconButton: ({ icon, onClick }: { icon: { value: string }, onClick?: () => void }) => (
      <antd.Button
        aria-label={ icon.value }
        onClick={ onClick }
      />
    )
  }
})
jest.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
jest.mock('../mapping-step.styles', () => ({ useStyles: () => ({ styles: {} }) }))
jest.mock('./arrow-column/arrow-column', () => ({ ArrowColumn: () => null }))

const renderRow = (readOnly: boolean, onOpenAdvanced = jest.fn(), onRemove = jest.fn()): void => {
  render(
    <ConfigEditorModeProvider readOnly={ readOnly }>
      <Form disabled={ readOnly }>
        <MappingItemContent
          attributeOptions={ [] }
          columnHeaderOptions={ [{ value: 'sku', label: 'SKU' }] }
          dataSourceIndex={ ['sku'] }
          fieldIndex={ 0 }
          isAdvanced={ false }
          isInProgressState={ false }
          isLocalized={ false }
          isWarningState={ false }
          itemLabel="SKU"
          language={ undefined }
          languageOptions={ [] }
          onOpenAdvanced={ onOpenAdvanced }
          onRemove={ onRemove }
          selectedAttr={ undefined }
          selectedFieldName="sku"
        />
      </Form>
    </ConfigEditorModeProvider>
  )
}

afterEach(cleanup)

describe('MappingItemContent', () => {
  it('lets a reader open Advanced while every edit stays closed', () => {
    const onOpenAdvanced = jest.fn()
    renderRow(true, onOpenAdvanced)

    const advanced = screen.getByRole('button', { name: 'data-importer.mapping.item.advanced' })
    expect(advanced.hasAttribute('disabled')).toBe(false)
    fireEvent.click(advanced)
    expect(onOpenAdvanced).toHaveBeenCalledTimes(1)

    expect(screen.queryByRole('button', { name: 'trash' })).toBeNull()
    expect(screen.getByPlaceholderText('data-importer.mapping.item.label').hasAttribute('disabled')).toBe(true)
  })

  it('offers removing the row and editing its label to an editor', () => {
    const onRemove = jest.fn()
    renderRow(false, jest.fn(), onRemove)

    fireEvent.click(screen.getByRole('button', { name: 'trash' }))
    expect(onRemove).toHaveBeenCalledTimes(1)
    expect(screen.getByPlaceholderText('data-importer.mapping.item.label').hasAttribute('disabled')).toBe(false)
  })
})
