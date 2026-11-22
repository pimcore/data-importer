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
import { PreviewFooter, StepFooter } from './dialog-footers'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../test/support/ad-hoc-import'),
  // each class name is its css with every token read as its own name
  createStyles: (build: (theme: { token: object, css: typeof String.raw }) => object) => () => ({
    styles: build({ token: new Proxy({}, { get: (_, key) => String(key) }), css: String.raw })
  })
}))
jest.mock('@pimcore/studio-ui-bundle/components', () => jest.requireActual<{ components: object }>('../../../../test/support/ad-hoc-import').components)

const footer = (undecided?: number): void => {
  render(
    <StepFooter
      canGoOn
      isFirstStep={ false }
      onBack={ () => {} }
      onClose={ () => {} }
      onNext={ () => {} }
      undecided={ undecided }
    />
  )
}

afterEach(() => {
  cleanup()
})

describe('the footer of a step', () => {
  it('counts the columns still open in the mapping', () => {
    footer(3)

    expect(screen.getByText('3 columns not decided yet: choose a field or “Don\'t import”')).toBeTruthy()
  })

  it('names nothing outside the mapping', () => {
    footer()

    expect(screen.queryByText(/decided/)).toBeNull()
  })
})

describe('the footer of the preview', () => {
  it('writes the label of the error mode in the secondary text colour', () => {
    render(
      <PreviewFooter
        errorMode="skip"
        importCount={ 2 }
        onBack={ () => {} }
        onErrorModeChange={ () => {} }
        onImport={ () => {} }
      />
    )

    expect(screen.getByText('Rows with errors').className).toMatch(/color: colorTextSecondary;/)
  })
})
