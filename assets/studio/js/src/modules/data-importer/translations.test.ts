/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { readFileSync } from 'fs'
import { resolve } from 'path'

const translationsDir = resolve(__dirname, '../../../../../../src/Resources/translations')

// The Studio translation files hold flat "key: text" lines; a text may be single-quoted ('' for a quote).
const readTranslations = (language: string): Map<string, string> => {
  const entries = new Map<string, string>()
  const lines = readFileSync(resolve(translationsDir, `studio.${language}.yaml`), 'utf8').split('\n')

  for (const line of lines) {
    const match = /^([^\s#][^:]*):\s*(.*)$/.exec(line)
    if (match === null) {
      continue
    }

    let text = match[2].trim()
    if (text.startsWith("'") && text.endsWith("'") && text.length >= 2) {
      text = text.slice(1, -1).replace(/''/g, "'")
    } else if (text.startsWith('"') && text.endsWith('"') && text.length >= 2) {
      text = text.slice(1, -1)
    }
    entries.set(match[1], text)
  }

  return entries
}

const placeholders = (text: string): string[] => (text.match(/{{\s*\w+\s*}}/g) ?? []).sort()

const english = readTranslations('en')
const previewKeys = [
  ...[...english.keys()].filter(key => key.startsWith('data-importer.mapping.preview-mode.')),
  'data-importer.mapping.advanced-modal.preview-error'
]

describe('Studio translations of the mapping preview', () => {
  it('finds the preview keys in the English file', () => {
    expect(previewKeys.length).toBeGreaterThanOrEqual(12)
    expect(english.get('data-importer.mapping.advanced-modal.preview-error')).toBeDefined()
  })

  it.each(['de', 'es', 'fr', 'it', 'nl', 'no', 'pl', 'sv'])('AC1: every preview key is translated with the same placeholders in %s', (language) => {
    const translations = readTranslations(language)

    for (const key of previewKeys) {
      const text = translations.get(key)

      expect({ key, present: text !== undefined && text !== '' }).toEqual({ key, present: true })
      expect({ key, placeholders: placeholders(text ?? '') }).toEqual({ key, placeholders: placeholders(english.get(key) ?? '') })
    }
  })
})
