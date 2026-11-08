/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { languageOfLabel } from './mapping-choices'

describe('the language a column name ends in', () => {
  it('reads a language suffix as the server does', () => {
    const languages = ['en', 'de', 'en_GB', 'fr']

    expect(languageOfLabel('Width DE', languages)).toBe('de')
    expect(languageOfLabel(' Name (en) ', languages)).toBe('en')
    expect(languageOfLabel('Name_en_GB', languages)).toBe('en_GB')
    expect(languageOfLabel('Name en_DE', languages)).toBe('en')
    expect(languageOfLabel('Name de_en', languages)).toBe('en')
    expect(languageOfLabel('Name DE-AT', languages)).toBe('de')
    expect(languageOfLabel('Name fr-Latn', languages)).toBeNull()
    expect(languageOfLabel('Name IT', languages)).toBeNull()
    expect(languageOfLabel('Name', languages)).toBeNull()
    expect(languageOfLabel('DE', languages)).toBeNull()
  })

  it('needs a name before the language suffix', () => {
    const languages = ['en', 'en_GB']

    expect(languageOfLabel('_en', languages)).toBeNull()
    expect(languageOfLabel('-(en)', languages)).toBeNull()
    expect(languageOfLabel('_en_GB', languages)).toBeNull()
    expect(languageOfLabel('__en', languages)).toBe('en')
    expect(languageOfLabel('Name -- (en_GB)', languages)).toBe('en_GB')
    expect(languageOfLabel(`${'- '.repeat(20000)}x`, languages)).toBeNull()
  })

  it('reads a line break as the server does: in the name none, before the suffix a separator', () => {
    const languages = ['en', 'de', 'en_GB']

    expect(languageOfLabel('Na\nme DE', languages)).toBeNull()
    expect(languageOfLabel('Na\nme en_GB', languages)).toBeNull()
    expect(languageOfLabel('Name\nDE', languages)).toBe('de')
    expect(languageOfLabel('Name \n (en_GB)', languages)).toBe('en_GB')
    expect(languageOfLabel('_\nen', languages)).toBe('en')
  })
})
