/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import {
  annotationsFor,
  configChanges,
  groupChanges,
  isNewConfiguration,
  mappingAnnotations,
  proposedConfiguration,
  SECTION_TARGET,
  toFormPath
} from './config-review-model'
import type { ReviewPayload } from './review-payload'
import type { BackendConfiguration } from '../utils/transformers'

// the form-annotation seam reads Studio's components at runtime; the model needs none of them
jest.mock('@pimcore/studio-ui-bundle/components', () => ({}), { virtual: true })

const live = {
  general: { name: 'car-import', active: true, modificationDate: 1700000000 },
  loaderConfig: { type: 'sftp', settings: { host: 'old.example.com' } },
  interpreterConfig: { type: 'csv' },
  mappingConfig: [
    { mappingId: 'a', label: 'VIN', dataSourceIndex: ['0'], dataTarget: { type: 'direct', settings: { fieldName: 'vin' } } }
  ]
}

const review: ReviewPayload = {
  slots: {
    general: {
      shape: 'record',
      current: { 'general.name': 'car-import', 'general.active': true },
      proposed: { 'general.name': 'car-import', 'general.active': false }
    },
    dataSource: {
      shape: 'record',
      current: { 'loaderConfig.settings.host': 'old.example.com', 'interpreterConfig.type': 'csv' },
      proposed: { 'loaderConfig.settings.host': 'new.example.com', 'interpreterConfig.type': 'csv', 'loaderConfig.bogus': 1 }
    },
    mapping: {
      shape: 'record',
      current: { mappingConfig: live.mappingConfig },
      proposed: {
        mappingConfig: [
          { mappingId: 'a', label: 'VIN', dataSourceIndex: ['0'], dataTarget: { type: 'direct', settings: { fieldName: 'chassis' } } }
        ]
      }
    }
  }
}

describe('config review model', () => {
  it('lists each changed loader field once, in its section, bound to its form field', () => {
    const changes = configChanges(review)

    expect(changes.map((change) => change.address)).toEqual([
      'general.active',
      'loaderConfig.settings.host',
      'loaderConfig.bogus'
    ])
    expect(changes[1]).toMatchObject({
      section: 'dataSource',
      status: 'changed',
      formPath: 'loaderConfig.settings.host',
      unbound: false,
      current: 'old.example.com',
      proposed: 'new.example.com'
    })
  })

  it('flags a field the configuration does not have, and binds it to nothing', () => {
    const bogus = configChanges(review).find((change) => change.address === 'loaderConfig.bogus')

    expect(bogus).toMatchObject({ unbound: true, status: 'added', formPath: undefined })
  })

  it('maps a change to the tab and step the editor shows it on', () => {
    const groups = groupChanges(configChanges(review))

    expect(groups.map((group) => group.section)).toEqual(['general', 'dataSource'])
    expect(SECTION_TARGET[groups[1].section]).toEqual({ tab: 'data-setup', step: 0 })
    expect(SECTION_TARGET.mapping).toEqual({ tab: 'data-setup', step: 3 })
  })

  it('marks the fields the form binds, general ones at the form root', () => {
    expect(annotationsFor(configChanges(review))).toEqual({
      active: { status: 'changed' },
      'loaderConfig.settings.host': { status: 'changed' }
    })
    expect(toFormPath('general.modificationDate')).toBeUndefined()
    expect(toFormPath('general.description')).toBe('description')
  })

  it('marks a changed mapping row on the row', () => {
    expect(mappingAnnotations(review, 'review')).toEqual({ 'mappingConfig.0': { status: 'changed' } })
  })

  it('lays the proposal over the live configuration, leaving the rest as stored', () => {
    const config = proposedConfiguration(review, live as unknown as BackendConfiguration) as unknown as typeof live

    expect(config.loaderConfig).toMatchObject({ type: 'sftp', settings: { host: 'new.example.com' } })
    expect(config.general.active).toBe(false)
    expect(config.general.modificationDate).toBe(1700000000)
    expect(config.mappingConfig[0].dataTarget.settings.fieldName).toBe('chassis')
    // the live document is not touched
    expect(live.loaderConfig.settings.host).toBe('old.example.com')
  })

  it('tells a new configuration from a changed one', () => {
    const created: ReviewPayload = {
      slots: { general: { shape: 'record', current: {}, proposed: { 'general.active': false } } }
    }

    expect(isNewConfiguration(created)).toBe(true)
    expect(isNewConfiguration(review)).toBe(false)
    expect(isNewConfiguration({ slots: {} })).toBe(false)
  })
})
