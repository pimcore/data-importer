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
import { ImportStep } from './import-step'
import { type ImportRun } from './import-state'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: { error: 'error' } })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../../test/support/ad-hoc-import').components)
jest.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  useElementHelper: () => ({ openElement: mockOpenElement })
}))

const mockOpenElement = jest.fn()

const run = (given: Partial<ImportRun>): ImportRun => ({
  state: 'done',
  total: 309,
  processed: 309,
  updated: 298,
  created: 11,
  failed: 0,
  skipped: 3,
  errorMode: 'values',
  fileName: 'northhike-autumn.xlsx',
  matchColumn: 'SKU',
  newObjectsFolder: '/Products/Outdoor',
  newObjectsFolderId: 7,
  userName: 'Jo Doe',
  finishedAt: 1_800_000_000,
  failures: [],
  ...given
})

afterEach(() => {
  cleanup()
  jest.resetAllMocks()
})

describe('the running import', () => {
  it('shows the count from the preview until the server has answered', () => {
    render(<ImportStep
      fileId="file-1"
      run={ undefined }
      total={ 306 }
           />)

    expect(screen.getByText('Importing 306 objects')).toBeTruthy()
    expect(screen.getAllByText('0 of 306')).toHaveLength(2)
    expect(screen.getByText('Runs in the background. You can close this window; Studio tells you when the import is done.')).toBeTruthy()
  })

  it('follows the run while it goes on', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({ state: 'running', processed: 184, total: 306 }) }
      total={ 306 }
           />)

    expect(screen.getByText('Importing 306 objects')).toBeTruthy()
    expect(screen.getAllByText('184 of 306')).toHaveLength(2)
    expect(screen.getByText('Saving')).toBeTruthy()
    expect(screen.getByRole('progressbar').getAttribute('value')).toBe('60')
  })
})

describe('an import that saved every row', () => {
  it('shows what it did, and offers the folder and the rows skipped in the preview', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({}) }
      total={ 309 }
           />)

    expect(screen.getByText('Import complete')).toBeTruthy()
    expect(screen.getByText('309')).toBeTruthy()
    expect(screen.getByText('objects imported from northhike-autumn.xlsx')).toBeTruthy()
    expect(screen.getByText(/^Saved as Jo Doe · /)).toBeTruthy()
    expect(screen.getByText('Every object keeps its version history.')).toBeTruthy()
    expect(within(screen.getByText('Skipped in the preview').parentElement!).getByText('3')).toBeTruthy()
    expect(screen.getByText('Created in /Products/Outdoor')).toBeTruthy()

    const download = screen.getByRole('link', { name: 'Download rows' })
    expect(download.getAttribute('href')).toBe('/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/file-1/skipped-rows?errorMode=values')

    fireEvent.click(screen.getByRole('button', { name: 'Open folder' }))
    expect(mockOpenElement).toHaveBeenCalledWith({ id: 7, type: 'data-object' })
  })

  it('names no download when no row was skipped', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({ skipped: 0 }) }
      total={ 309 }
           />)

    expect(screen.queryByRole('link', { name: 'Download rows' })).toBeNull()
    expect(screen.queryByText('Skipped in the preview')).toBeNull()
  })
})

describe('an import with rows that were not saved', () => {
  const failed = run({
    updated: 296,
    created: 9,
    failed: 4,
    failures: [
      { row: 41, matchValue: 'NH-1090', action: 'create', message: '/Products/Outdoor already holds an object named NH-1090' },
      { row: 77, matchValue: 'NH-1121', action: 'update', message: 'You may not save objects in /Products/Outdoor/Archive' },
      { row: 120, matchValue: 'NH-1155', action: 'create', message: 'A value must be unique, and another object holds it' },
      { row: 203, matchValue: 'SB-310', action: null, message: 'Saving failed; the application log has the details' }
    ]
  })

  it('counts them and lists them with why', () => {
    render(<ImportStep
      fileId="file-1"
      run={ failed }
      total={ 309 }
           />)

    expect(screen.getByRole('alert').textContent).toContain('4 rows were not saved.')
    expect(screen.getByRole('alert').textContent).toContain('The others are saved, each with its version history.')
    expect(within(screen.getByText('not saved').parentElement!).getByText('4')).toBeTruthy()
    expect(within(screen.getByText('updated').parentElement!).getByText('296')).toBeTruthy()
    expect(within(screen.getByText('created in /Products/Outdoor').parentElement!).getByText('9')).toBeTruthy()
    expect(within(screen.getByText('skipped in the preview').parentElement!).getByText('3')).toBeTruthy()

    expect(screen.getByRole('columnheader', { name: 'Why it was not saved' })).toBeTruthy()
    expect(screen.getByRole('columnheader', { name: 'SKU' })).toBeTruthy()
    const row = screen.getByText('NH-1121').closest('tr') as HTMLElement
    expect(within(row).getByText('77')).toBeTruthy()
    expect(within(row).getByText('update')).toBeTruthy()
    expect(within(row).getByText('You may not save objects in /Products/Outdoor/Archive').className).toBe('error')
    expect(within(screen.getByText('SB-310').closest('tr') as HTMLElement).queryByText('update')).toBeNull()
    expect(screen.queryByText('Import complete')).toBeNull()
  })
})
