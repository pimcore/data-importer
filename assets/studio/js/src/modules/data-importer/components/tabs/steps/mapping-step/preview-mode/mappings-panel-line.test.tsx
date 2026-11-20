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
import { MappingsPanelContent, type MappingsPanelContentProps } from '../mappings-panel/mappings-panel-content'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
// the line's upload/copy hint is out of scope here
jest.mock('../../../../../data-importer-api-slice.gen', () => ({}))
// the rows are out of scope here
jest.mock('../mapping-item', () => ({
  MappingDropZone: () => null,
  MappingItemWithFilter: ({ fieldIndex }: { fieldIndex: number }) => <span data-testid="mapping">{ fieldIndex }</span>
}))
jest.mock('../mappings-panel/empty-drop-zone', () => ({ EmptyDropZone: () => null }))
jest.mock('../mappings-panel/filtered-empty-state', () => ({ FilteredEmptyState: () => null }))

const noop = (): void => undefined

const props = (overrides: Partial<MappingsPanelContentProps>): MappingsPanelContentProps => ({
  fields: [{ name: 0, key: 10 }, { name: 1, key: 11 }],
  add: noop,
  remove: noop,
  hasItems: true,
  flashIndex: null,
  expandedKeys: new Set([10]),
  allVisibleCollapsed: false,
  activeFilter: null,
  activeFilterLabel: null,
  onCollapseAll: noop,
  onNewKey: noop,
  onToggleKey: noop,
  onOpenAutofillSuggestions: noop,
  onAddItem: noop,
  onRemoveItem: noop,
  onAddMappingForFilter: noop,
  onInsertItem: noop,
  onDropped: noop,
  getMappingIdByIndex: (index) => `m${index}`,
  ...overrides
})

afterEach(cleanup)

describe('MappingsPanelContent with the preview mode line', () => {
  it('AC1: Mappings headline keeps New and Autofill, Collapse all moves into the preview mode line below it', () => {
    const onCollapseAll = jest.fn()
    render(<MappingsPanelContent { ...props({ onCollapseAll }) } />)

    const line = screen.getByRole('toolbar', { name: 'data-importer.mapping.preview-mode.switch' })
    expect(within(line).getByRole('switch', { name: 'data-importer.mapping.preview-mode.switch' })).toBeTruthy()
    expect(within(line).queryByRole('button', { name: 'data-importer.mapping.add' })).toBeNull()
    expect(within(line).queryByRole('button', { name: 'data-importer.mapping.auto-fill' })).toBeNull()
    expect(screen.getAllByRole('button', { name: 'data-importer.mapping.collapse-all' })).toHaveLength(1)

    fireEvent.click(within(line).getByRole('button', { name: 'data-importer.mapping.collapse-all' }))
    expect(onCollapseAll).toHaveBeenCalledWith([10, 11])
    // the headline comes first, the line follows it
    const headline = screen.getByText('data-importer.mapping.title-short')
    expect(headline.compareDocumentPosition(line) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })

  it('AC1: the line offers Expand all when every mapping is folded', () => {
    render(<MappingsPanelContent { ...props({ allVisibleCollapsed: true, expandedKeys: new Set() }) } />)

    const line = screen.getByRole('toolbar', { name: 'data-importer.mapping.preview-mode.switch' })
    expect(within(line).getByRole('button', { name: 'data-importer.mapping.expand-all' })).toBeTruthy()
  })
})
