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
import { StepFooter } from './dialog-footers'

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  ...jest.requireActual<object>('../../../../test/support/ad-hoc-import'),
  createStyles: () => () => ({ styles: {} })
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
