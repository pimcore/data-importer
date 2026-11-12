/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useClassificationStoreSettings } from './use-classification-store-settings'
import { type DynamicTypeDataTargetRenderProps } from '../common/dynamic-type-data-target-abstract'

// The hook is called as a plain function, as the components of this directory are, so React's own
// hooks stand in as pass-throughs.
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({
  ApiError: class {},
  trackError: jest.fn()
}))

jest.mock('react', () => ({
  ...jest.requireActual('react'),
  useMemo: (factory: () => unknown) => factory(),
  useRef: (initial: unknown) => ({ current: initial }),
  useEffect: () => {}
}))

const FIELD = 'csField'

let mockAttributes: Array<Record<string, unknown>> = []
let mockKeyName: Record<string, unknown> | undefined
let mockKeyError = false
let mockSkipped: boolean | undefined
let mockClassId: string | undefined = 'Product'

jest.mock('../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterClassificationstoreLoadAttributesQuery: () => ({
    data: { attributes: mockAttributes },
    isFetching: false
  }),
  useBundleDataImporterClassificationstoreLoadKeyNameQuery: (_args: unknown, options: { skip: boolean }) => {
    mockSkipped = options.skip
    return { currentData: mockKeyName, isError: mockKeyError, error: mockKeyError ? {} : undefined }
  }
}))

function resolution (): { isLocalized: boolean, isKeyLocalized: boolean, isKeyUnanswered: boolean } {
  const props: DynamicTypeDataTargetRenderProps = {
    classId: mockClassId,
    classFieldOptions: [],
    isLocalized: false,
    transformationResultType: 'default',
    settings: { fieldName: FIELD, keyId: 'grp:key' },
    onChange: jest.fn()
  }

  const { isLocalized, isKeyLocalized, isKeyUnanswered } = useClassificationStoreSettings(props)

  return { isLocalized, isKeyLocalized, isKeyUnanswered }
}

describe('the localization the classification store settings resolve', () => {
  beforeEach(() => {
    // the field says the opposite of every key below, so only the key's answer can be read
    mockAttributes = [{ key: FIELD, title: FIELD, localized: true }]
    mockKeyName = undefined
    mockKeyError = false
    mockClassId = 'Product'
  })

  it('reports the key localized when the key-name endpoint says it is', () => {
    mockKeyName = { localized: true }

    expect(resolution().isKeyLocalized).toBe(true)
  })

  it('reports the key not localized when the endpoint says it is not, whatever the field says', () => {
    mockKeyName = { localized: false }

    const { isLocalized, isKeyLocalized } = resolution()
    expect(isKeyLocalized).toBe(false)
    expect(isLocalized).toBe(true)
  })

  it('reports the key not localized while no answer for it is held', () => {
    expect(resolution().isKeyLocalized).toBe(false)
  })

  it('reports the key unanswered, not localized, while no answer for it is held', () => {
    expect(resolution().isKeyUnanswered).toBe(true)
  })

  it('reports the key answered once the endpoint has answered', () => {
    mockKeyName = { localized: false }

    expect(resolution().isKeyUnanswered).toBe(false)
  })

  it('reports the key not localized when the answer carries no flag at all', () => {
    mockKeyName = { keyName: 'Width' }

    expect(resolution().isKeyLocalized).toBe(false)
  })

  it('keeps answering the field flag for the batch target', () => {
    mockAttributes = [{ key: FIELD, title: FIELD, localized: false }]
    mockKeyName = { localized: true }

    expect(resolution().isLocalized).toBe(false)
  })

  it('does not report a key unanswered once its request has failed', () => {
    mockKeyError = true

    const { isKeyUnanswered, isKeyLocalized } = resolution()
    expect(isKeyUnanswered).toBe(false)
    expect(isKeyLocalized).toBe(false)
  })

  it('does not ask for a key without a class, and reads the key as unanswered rather than not localized', () => {
    mockClassId = undefined

    const { isKeyUnanswered, isKeyLocalized } = resolution()
    expect(mockSkipped).toBe(true)
    expect(isKeyUnanswered).toBe(true)
    expect(isKeyLocalized).toBe(false)
  })
})
