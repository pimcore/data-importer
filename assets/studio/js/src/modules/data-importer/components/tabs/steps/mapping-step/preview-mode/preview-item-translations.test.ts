/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { readdirSync, readFileSync } from 'fs'
import { resolve } from 'path'

const translationsDir = resolve(__dirname, '../../../../../../../../../../../src/Resources/translations')

describe('Translations without Preview item (platform-version#858)', () => {
  it('AC3: no translations file has the data-importer.mapping.preview-mode.preview-item key', () => {
    const files = readdirSync(translationsDir).filter(file => /\.ya?ml$/.test(file))
    expect(files).toContain('studio.en.yaml')

    const withKey = files.filter(file =>
      /^data-importer\.mapping\.preview-mode\.preview-item\s*:/m.test(readFileSync(resolve(translationsDir, file), 'utf8'))
    )

    expect(withKey).toEqual([])
  })
})
