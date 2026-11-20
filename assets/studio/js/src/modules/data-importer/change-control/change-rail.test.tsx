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
import { cleanup, render } from '@testing-library/react'
import { ChangeList } from './change-rail'
import { ConfigBriefCard } from './config-outline-rail'
import type { ConfigChange, ChangeGroup } from './config-review-model'
import type { MappingRowDiff } from './mapping-diff'

const summaries: Array<Record<string, unknown>> = []

jest.mock('@pimcore/change-control-bundle/sdk', () => ({
  ConfigSummary: (props: Record<string, unknown>) => {
    summaries.push(props)
    return null
  }
}))

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useTranslation: () => ({
    t: (key: string, options?: Record<string, unknown>) => options === undefined ? key : `${key} ${JSON.stringify(options)}`
  })
}))

const change = (section: string, address: string, unbound = false): ConfigChange => ({
  address, unbound, label: address, section, status: 'changed', current: undefined, proposed: undefined
})

const group = (section: string, ...changes: ConfigChange[]): ChangeGroup => ({ section, label: `label.${section}`, changes })

const mapping = (key: string, status: MappingRowDiff['status']): MappingRowDiff => ({
  key, label: key, target: 'name (direct)', status, row: {}
})

const last = (): Record<string, any> => summaries[summaries.length - 1]

beforeEach(() => { summaries.length = 0 })
afterEach(cleanup)

describe('ChangeList', () => {
  const props = {
    configuration: { general: { name: 'car-import' } } as any,
    activeSection: 'resolver',
    labelFor: (c: ConfigChange) => `field ${c.address}`,
    onJump: jest.fn()
  }

  it('puts the mapping rows right after the resolver and says what the editor cannot show', () => {
    render(
      <ChangeList
        { ...props }
        groups={ [
          group('general', change('general', 'general.description')),
          group('resolver', change('resolver', 'resolverConfig.x', true)),
          group('processing', change('processing', 'processingConfig.y'))
        ] }
        mappings={ [mapping('m1', 'changed'), mapping('m2', 'unchanged')] }
      />
    )

    const summary = last()
    expect(summary.name).toBe('car-import')
    expect(summary.activeKey).toBe('resolver')
    expect(summary.onOpenSection).toBe(props.onJump)
    expect(summary.sections.map((s: any) => s.key)).toEqual(['general', 'resolver', 'mapping', 'processing'])
    expect(summary.sections[0]).toEqual({
      key: 'general',
      label: 'label.general',
      rows: [{
        key: 'general.description',
        label: 'field general.description',
        hint: 'general.description',
        note: undefined,
        status: 'changed'
      }]
    })
    expect(summary.sections[1].rows[0].note).toBe('data-importer.review.unbound {"address":"resolverConfig.x"}')
    expect(summary.sections[2]).toEqual({
      key: 'mapping',
      label: 'data-importer.review.mappings',
      rows: [
        { key: 'm1', label: 'm1', status: 'changed' },
        { key: 'm2', label: 'm2', status: undefined }
      ]
    })
  })

  it('says what a changed value was and becomes, and how much a list grew or shrank', () => {
    const changed = (address: string, current: unknown, proposed: unknown, status: ConfigChange['status'] = 'changed'): ConfigChange => ({
      address, unbound: false, label: address, section: 'dataSource', status, current, proposed
    })
    render(
      <ChangeList
        { ...props }
        groups={ [group('dataSource',
          changed('loaderConfig.settings.host', 'old.example.com', 'new.example.com'),
          changed('loaderConfig.settings.port', 21, 22),
          changed('loaderConfig.settings.list', ['a', 'b'], ['a']),
          changed('loaderConfig.settings.same', ['a', 'b'], ['a', 'c']),
          changed('loaderConfig.settings.obj', { a: 1 }, { a: 2 }),
          changed('loaderConfig.settings.added', undefined, 'x', 'added')
        )] }
        mappings={ [] }
      />
    )

    expect(last().sections[0].rows.map((row: any) => row.note)).toEqual([
      'old.example.com → new.example.com',
      '21 → 22',
      '2 → 1',
      undefined,
      undefined,
      undefined
    ])
  })

  it('never prints a secret, only that it changed', () => {
    const changed = (address: string, current: unknown, proposed: unknown): ConfigChange => ({
      address, unbound: false, label: address, section: 'dataSource', status: 'changed', current, proposed
    })
    render(
      <ChangeList
        { ...props }
        groups={ [group('dataSource',
          changed('loaderConfig.settings.password', 's3cret', 'n3w'),
          changed('loaderConfig.settings.apiKey', 'key-old', 'key-new'),
          changed('loaderConfig.settings.clientSecret', 'a', 'b'),
          changed('loaderConfig.settings.accessToken', 'c', 'd')
        )] }
        mappings={ [] }
      />
    )

    const rows = last().sections[0].rows
    expect(rows.map((row: any) => row.note)).toEqual([undefined, undefined, undefined, undefined])
    expect(rows.map((row: any) => row.status)).toEqual(['changed', 'changed', 'changed', 'changed'])
  })

  it('puts the mapping rows last without a resolver change, and leaves them out when there are none', () => {
    render(
      <ChangeList
        { ...props }
        configuration={ { general: {} } as any }
        groups={ [group('general', change('general', 'general.description'))] }
        mappings={ [mapping('m1', 'added')] }
      />
    )
    expect(last().sections.map((s: any) => s.key)).toEqual(['general', 'mapping'])
    expect(last().name).toBe('')

    render(
      <ChangeList
        { ...props }
        groups={ [group('general', change('general', 'general.description'))] }
        mappings={ [] }
      />
    )
    expect(last().sections.map((s: any) => s.key)).toEqual(['general'])
  })
})

describe('ConfigBriefCard', () => {
  it('says what each section of a new configuration is set to', () => {
    const onJump = jest.fn()
    render(
      <ConfigBriefCard
        activeSection="dataSource"
        brief={ {
          name: 'car-import',
          active: false,
          description: 'Cars',
          sections: [
            { key: 'dataSource', label: 'label.dataSource', value: 'Asset', note: 'JSON' },
            { key: 'execution', label: 'label.execution' }
          ]
        } }
        onJump={ onJump }
      />
    )

    expect(last()).toEqual({
      active: false,
      activeKey: 'dataSource',
      description: 'Cars',
      name: 'car-import',
      onOpenSection: onJump,
      variant: 'description',
      sections: [
        { key: 'dataSource', label: 'label.dataSource', rows: [{ key: 'dataSource', label: 'Asset', note: 'JSON' }] },
        { key: 'execution', label: 'label.execution', rows: [] }
      ]
    })
  })
})
