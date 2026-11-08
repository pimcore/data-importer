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
import { type MappingSuggestion } from '../utils/autofill-suggestions'
import { AutofillSuggestionsPanel } from './autofill-suggestions-panel'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
jest.mock('@pimcore/studio-ui-bundle/components', () => ({
  Checkbox: () => null,
  Flex: ({ children }: { children: React.ReactNode }) => <div>{ children }</div>,
  IconButton: () => null,
  NoContent: ({ text }: { text: string }) => <span>{ text }</span>,
  Spin: () => <span data-testid="spin" />,
  Tag: ({ children }: { children: React.ReactNode }) => <span data-testid="tag">{ children }</span>
}))
jest.mock('@pimcore/studio-ui-bundle/utils', () => ({ uuid: () => 'id' }))
jest.mock('./autofill-suggestions-panel.styles', () => ({ useStyles: () => ({ styles: {} }) }))
jest.mock('../utils/mapping-factory', () => ({ createMappingItem: jest.fn() }))
jest.mock('../mapping-item/arrow-column/mapping-arrow-icon.inline', () => ({ MappingArrowIcon: () => null }))

const suggestion = (source: 'name' | 'ai', language: string | null = null): MappingSuggestion => ({
  id: `${source}-${language}`,
  sourceIndex: 'col',
  sourceLabel: 'Col',
  targetFieldName: 'field',
  targetFieldLabel: 'Field [field]',
  transformationResultType: 'default',
  score: 90,
  language,
  source,
  previewResult: null
})

const panel = (suggestions: MappingSuggestion[], isLoadingAiSuggestions?: boolean): React.JSX.Element => (
  <AutofillSuggestionsPanel
    hasPrevRow={ false }
    isLoadingAiSuggestions={ isLoadingAiSuggestions }
    isLoadingPreviewRow={ false }
    onNextRow={ jest.fn() }
    onPrevRow={ jest.fn() }
    onToggle={ jest.fn() }
    previewRow={ {} }
    selectedIds={ new Set() }
    suggestions={ suggestions }
  />
)

const tags = (): Array<string | null> => screen.queryAllByTestId('tag').map(tag => tag.textContent)

afterEach(cleanup)

describe('AutofillSuggestionsPanel', () => {
  it('marks a suggestion of the AI stage, not one of the name comparison', () => {
    render(panel([suggestion('name')]))
    expect(tags()).toEqual([])
    cleanup()

    render(panel([suggestion('ai', 'de')]))
    expect(tags()).toEqual(['de', 'data-importer.mapping.autofill-suggestions.source-ai'])
  })

  it('says there are no suggestions only once the AI stage ended', () => {
    render(panel([], true))
    expect(screen.queryByText('data-importer.mapping.autofill-suggestions.empty')).toBeNull()
    expect(screen.getAllByTestId('spin')).toHaveLength(1)
    cleanup()

    render(panel([]))
    expect(screen.getByText('data-importer.mapping.autofill-suggestions.empty')).toBeTruthy()
    expect(screen.queryByTestId('spin')).toBeNull()
  })

  it('shows that more are coming below the name matches', () => {
    render(panel([suggestion('name')], true))
    expect(screen.getAllByTestId('spin')).toHaveLength(1)
    cleanup()

    render(panel([suggestion('name')]))
    expect(screen.queryByTestId('spin')).toBeNull()
  })
})
