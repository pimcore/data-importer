/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { fieldLabel, humanize, translated } from './field-labels'
import type { BackendConfiguration } from '../utils/transformers'

const LABELS: Record<string, string> = {
  'data-importer.resolver.class': 'Class',
  'data-importer.loader.sftp.remote-path': 'Remote path',
  'data-importer.interpreter.csv.skip-first-row': 'Skip first row',
  'data-importer.resolver.create-location-strategy': 'Location Strategy'
}

const t = (key: string): string => LABELS[key] ?? key

const configuration = { loaderConfig: { type: 'sftp' }, interpreterConfig: { type: 'csv' } } as unknown as BackendConfiguration

describe('fieldLabel', () => {
  it('takes the label the editor shows next to the field', () => {
    expect(fieldLabel('resolverConfig.dataObjectClassId', configuration, t)).toBe('Class')
  })

  it('spells out a labelled field whose label has no translation', () => {
    expect(fieldLabel('processingConfig.doDeltaCheck', configuration, t)).toBe('Do Delta Check')
  })

  it('labels adapter settings by the adapter that is selected', () => {
    expect(fieldLabel('loaderConfig.settings.remotePath', configuration, t)).toBe('Remote path')
    expect(fieldLabel('interpreterConfig.settings.skipFirstRow', configuration, t)).toBe('Skip first row')
    expect(fieldLabel('loaderConfig.settings.port', configuration, t)).toBe('Port')
    expect(fieldLabel('interpreterConfig.settings.delimiter', undefined, t)).toBe('Delimiter')
  })

  it('names the strategy a strategy setting belongs to', () => {
    expect(fieldLabel('resolverConfig.createLocationStrategy.settings.path', configuration, t))
      .toBe('Location Strategy · Path')
    expect(fieldLabel('resolverConfig.publishingStrategy.settings.mode', configuration, t))
      .toBe('Publishing Strategy · Mode')
  })

  it('spells out anything else', () => {
    expect(fieldLabel('general.group_name', configuration, t)).toBe('Group name')
  })
})

describe('humanize and translated', () => {
  it('turns keys into words', () => {
    expect(humanize('doArchiveImportFile')).toBe('Do Archive Import File')
    expect(humanize('field-name')).toBe('Field name')
  })

  it('reads a key handed back as no translation', () => {
    expect(translated(t, 'data-importer.resolver.class')).toBe('Class')
    expect(translated(t, 'data-importer.unknown')).toBeUndefined()
  })
})
