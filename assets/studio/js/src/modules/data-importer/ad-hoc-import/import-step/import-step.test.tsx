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

jest.mock('@pimcore/studio-ui-bundle/api', () => ({ getPrefix: () => '/pimcore-studio/api' }))
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
  throughBranch: false,
  mergeTotal: 0,
  mergeDone: 0,
  notice: null,
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

  it('shows the run in a Studio card', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({ state: 'running', processed: 184, total: 306 }) }
      total={ 306 }
           />)

    expect(within(screen.getByTestId('studio-card')).getByText('Importing 306 objects')).toBeTruthy()
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
    expect(screen.getByTestId('result-bar').children).toHaveLength(3)

    const download = screen.getByRole('link', { name: 'Download rows' })
    expect(download.getAttribute('href')).toBe('/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file/file-1/skipped-rows?errorMode=values')

    fireEvent.click(screen.getByRole('button', { name: 'Open folder' }))
    expect(mockOpenElement).toHaveBeenCalledWith({ id: 7, type: 'data-object' })
  })

  it('draws each outcome of the result bar by its share', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({ updated: 300, created: 6, skipped: 3 }) }
      total={ 309 }
           />)

    expect(Array.from(screen.getByTestId('result-bar').children).map((segment) => (segment as HTMLElement).style.flexGrow)).toEqual(['300', '6', '3'])
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

  it('shows the result in a Studio card', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({}) }
      total={ 309 }
           />)

    expect(within(screen.getByTestId('studio-card')).getByText('Import complete')).toBeTruthy()
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

describe('an import through a branch', () => {
  it('shows the rows written into the branch while they are written', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({ state: 'running', throughBranch: true, total: 306, processed: 184 }) }
      total={ 306 }
           />)

    expect(screen.getByText('Written into a branch').parentElement!.getAttribute('aria-current')).toBe('step')
    expect(within(screen.getByText('Written into a branch').parentElement!).getByText('184 of 306')).toBeTruthy()
    expect(screen.getByText('Checking: rights, keys, values').parentElement!.getAttribute('aria-current')).toBeNull()
    expect(screen.getByText('Merging as Jo Doe')).toBeTruthy()
    expect(screen.queryByText('Saving')).toBeNull()
  })

  it('checks the branch until the merge writes, then counts what it wrote', () => {
    const merging = run({ state: 'merging', throughBranch: true, total: 306, processed: 306, mergeTotal: 306, mergeDone: 0 })
    render(<ImportStep
      fileId="file-1"
      run={ merging }
      total={ 306 }
           />)

    expect(within(screen.getByText('Written into a branch').parentElement!).getByText('306 objects')).toBeTruthy()
    expect(screen.getByText('Checking: rights, keys, values').parentElement!.getAttribute('aria-current')).toBe('step')

    cleanup()
    render(<ImportStep
      fileId="file-1"
      run={ { ...merging, mergeDone: 184 } }
      total={ 306 }
           />)

    expect(screen.getByText('Merging as Jo Doe').parentElement!.getAttribute('aria-current')).toBe('step')
    expect(within(screen.getByText('Merging as Jo Doe').parentElement!).getByText('184 of 306')).toBeTruthy()
  })

  it('shows no count while the branch is checked, as the check reports none, and keeps the rows written on the bar', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({ state: 'merging', throughBranch: true, total: 306, processed: 306, mergeTotal: 306, mergeDone: 0, failed: 0 }) }
      total={ 306 }
           />)

    expect(screen.queryByText('0 of 306')).toBeNull()
    expect(screen.getByRole('progressbar').getAttribute('value')).toBe('100')
    expect(within(screen.getByText('Checking: rights, keys, values').parentElement!).getByRole('status')).toBeTruthy()
  })

  it('names the merge in the result', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({ throughBranch: true }) }
      total={ 309 }
           />)

    expect(screen.getByText(/^Merged as Jo Doe · /)).toBeTruthy()
  })

  it('lists the rows that cannot be saved, with nothing merged yet', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({
        state: 'decision',
        throughBranch: true,
        updated: 300,
        created: 5,
        failed: 4,
        failures: [{ row: 41, matchValue: 'NH-1090', action: 'create', message: '/Products/Outdoor already holds an object named NH-1090' }]
      }) }
      total={ 309 }
           />)

    const alert = screen.getByRole('alert')
    expect(alert.textContent).toContain('4 rows cannot be saved.')
    expect(alert.textContent).toContain('Nothing is merged yet; the other rows are ready in the branch.')
    expect(within(screen.getByText('ready to merge').parentElement!).getByText('305')).toBeTruthy()
    expect(within(screen.getByText('cannot be saved').parentElement!).getByText('4')).toBeTruthy()
    expect(within(screen.getByText('skipped in the preview').parentElement!).getByText('3')).toBeTruthy()
    expect(screen.getByRole('columnheader', { name: 'Why it cannot be saved' })).toBeTruthy()
    expect(screen.getByText('/Products/Outdoor already holds an object named NH-1090').className).toBe('error')
  })

  it('says which import waits when the dialog opened on it, and what concerns no row', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({ state: 'decision', throughBranch: true, failed: 1, notice: 'The import could not be merged: The object no longer exists' }) }
      total={ 309 }
      waitingFile="autumn.xlsx"
           />)

    const alerts = screen.getAllByRole('alert').map((alert) => alert.textContent)
    expect(alerts[0]).toContain('An import of autumn.xlsx into this folder is waiting for your decision.')
    expect(alerts[1]).toContain('The import could not be merged: The object no longer exists')
  })

  it('tells what concerns no row when rows were not saved', () => {
    render(<ImportStep
      fileId="file-1"
      run={ run({ failed: 2, throughBranch: true, notice: 'The merge did not finish in time.' }) }
      total={ 309 }
           />)

    expect(screen.getAllByRole('alert')[0].textContent).toContain('The merge did not finish in time.')
  })
})
