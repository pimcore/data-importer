/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { configBrief } from './config-outline'
import type { BackendConfiguration } from '../utils/transformers'

// the form-annotation seam reads Studio's components at runtime; the outline needs none of them
jest.mock('@pimcore/studio-ui-bundle/components', () => ({}), { virtual: true })

const LABELS: Record<string, string> = {
  'data-importer.loader.asset': 'Asset',
  'data-importer.interpreter.csv': 'CSV',
  'data-importer.resolver.location-strategy.staticPath': 'Static Path',
  'data-importer.resolver.publishing-strategy.alwaysPublish': 'Always publish',
  'data-importer.processing.execution-type.sequential': 'Sequential',
  'data-importer.processing.delta-check': 'Delta check',
  'data-importer.processing.cleanup.title': 'Cleanup',
  'data-importer.processing.cleanup.strategy.unpublish': 'Unpublish',
  'data-importer.execution.manual-execution': 'Manual execution'
}

// the catalogue's own fallback: a key without a translation comes back as itself
const t = (key: string, options?: Record<string, unknown>): string =>
  LABELS[key] ?? (options === undefined ? key : `${key}(${JSON.stringify(options)})`)

const row = { dataSourceIndex: ['0'], dataTarget: { type: 'direct', settings: { fieldName: 'vin' } } }

const configuration = (): BackendConfiguration => ({
  general: { name: 'dealer-feed', active: false, description: 'Cars from the dealers', group: 'Feeds' },
  loaderConfig: { type: 'asset', settings: { assetPath: '/feeds/cars.csv' } },
  interpreterConfig: { type: 'csv', settings: { delimiter: ';' } },
  resolverConfig: {
    elementType: 'dataObject',
    dataObjectClassId: 'CAR',
    createLocationStrategy: { type: 'staticPath', settings: { path: '/import' } },
    publishingStrategy: { type: 'alwaysPublish' }
  },
  mappingConfig: [row, row],
  processingConfig: { executionType: 'sequential', doDeltaCheck: true, cleanup: { doCleanup: true, strategy: 'unpublish' } },
  executionConfig: { scheduleType: 'recurring', cronDefinition: '0 2 * * *' },
  permissions: {
    user: [{ name: 'editor', read: true, update: false, delete: false }],
    role: [{ name: 'importers', read: true, update: true, delete: false }]
  }
} as unknown as BackendConfiguration)

describe('configBrief', () => {
  it('tells each section of a new configuration in one line, in the order the editor shows them', () => {
    const brief = configBrief(configuration(), t)

    expect(brief).toMatchObject({ name: 'dealer-feed', active: false, description: 'Cars from the dealers' })
    expect(brief.sections.map((section) => section.key))
      .toEqual(['general', 'dataSource', 'resolver', 'mapping', 'processing', 'execution', 'permissions'])
    expect(brief.sections[0]).toEqual({ key: 'general', label: 'data-importer.review.section.general', value: 'Feeds' })
    expect(brief.sections[1]).toEqual({
      key: 'dataSource',
      label: 'data-importer.review.section.data-source',
      value: 'Asset · CSV',
      note: '/feeds/cars.csv · data-importer.review.outline.more-settings-one({"count":1})'
    })
    expect(brief.sections[2]).toEqual({
      key: 'resolver',
      label: 'data-importer.review.section.resolver',
      value: 'data-importer.review.outline.data-objects({"class":"CAR"})',
      note: 'Static Path /import · Always publish'
    })
    expect(brief.sections[4]).toMatchObject({ value: 'Sequential', note: 'Delta check · Cleanup: Unpublish' })
    expect(brief.sections[5]).toEqual({ key: 'execution', label: 'data-importer.review.section.execution', value: '0 2 * * *' })
  })

  it('labels the mapping section in the editor\'s words rather than its key', () => {
    const mapping = configBrief(configuration(), t).sections.find((section) => section.key === 'mapping')

    expect(mapping).toEqual({
      key: 'mapping',
      label: 'data-importer.mapping.title-short',
      value: 'data-importer.review.outline.mappings({"count":2})'
    })
  })

  // a permission list counts as its entries, not as their flags
  it('counts a section with nothing worth a line of its own', () => {
    const permissions = configBrief(configuration(), t).sections.find((section) => section.key === 'permissions')

    expect(permissions?.value).toBe('data-importer.review.outline.settings-count({"count":2})')
  })

  it('leaves out empty sections and falls back to spelt-out keys', () => {
    const brief = configBrief({
      general: { active: true, description: '', group: '' },
      loaderConfig: { type: 'sftp', settings: { host: 'ftp.example.org', remotePath: '/out/cars.csv' } },
      resolverConfig: { elementType: 'asset' },
      processingConfig: { executionType: 'parallel' },
      executionConfig: { scheduleType: 'job', scheduledAt: '2026-11-01 02:00' }
    } as unknown as BackendConfiguration, t)

    expect(brief).toMatchObject({ name: '', active: true, description: undefined })
    expect(brief.sections.map((section) => [section.key, section.value, section.note])).toEqual([
      ['dataSource', 'Sftp', 'ftp.example.org:/out/cars.csv'],
      ['resolver', 'Asset', undefined],
      ['processing', 'Parallel', undefined],
      ['execution', '2026-11-01 02:00', undefined]
    ])
  })

  it('says a configuration without a schedule runs by hand', () => {
    const brief = configBrief({ executionConfig: { scheduleType: 'none', cronDefinition: '' } } as unknown as BackendConfiguration, t)

    expect(brief.sections).toEqual([{
      key: 'execution',
      label: 'data-importer.review.section.execution',
      value: 'Manual execution'
    }])
  })
})
