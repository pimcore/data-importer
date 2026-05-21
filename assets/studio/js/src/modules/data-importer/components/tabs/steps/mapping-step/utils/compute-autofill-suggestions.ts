/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { uuid } from '@pimcore/studio-ui-bundle/utils'
import type { ClassAttribute, MappingConfigItem } from '../../../../../types'
import type { SourceRow } from '../sources-panel/sources-panel'
import { DEFAULT_ATTR_MAP_KEY } from '../../../../../types'

export interface MappingSuggestion {
  id: string
  sourceIndex: string
  sourceLabel: string
  targetFieldName: string
  targetFieldLabel: string
  score: number
  language: string | null
  previewResult: string | null
}

const MIN_SCORE = 40

// ─── ISO 639-1 language codes ─────────────────────────────────────────────────

// All 184 standard ISO 639-1 two-letter language codes.
const ISO_639_1_CODES = new Set([
  'ab', 'aa', 'af', 'ak', 'sq', 'am', 'ar', 'an', 'hy', 'as', 'av', 'ae', 'ay', 'az',
  'bm', 'ba', 'eu', 'be', 'bn', 'bh', 'bi', 'bs', 'br', 'bg', 'my',
  'ca', 'ch', 'ce', 'ny', 'zh', 'cv', 'kw', 'co', 'cr', 'hr', 'cs',
  'da', 'dv', 'nl', 'dz',
  'en', 'eo', 'et', 'ee',
  'fo', 'fj', 'fi', 'fr', 'ff',
  'gl', 'ka', 'de', 'el', 'gn', 'gu',
  'ht', 'ha', 'he', 'hz', 'hi', 'ho', 'hu',
  'ia', 'id', 'ie', 'ga', 'ig', 'ik', 'io', 'is', 'it', 'iu',
  'ja', 'jv',
  'kl', 'kn', 'kr', 'ks', 'kk', 'km', 'ki', 'rw', 'ky', 'kv', 'kg', 'ko', 'ku', 'kj',
  'la', 'lb', 'lg', 'li', 'ln', 'lo', 'lt', 'lu', 'lv', 'gv',
  'mk', 'mg', 'ms', 'ml', 'mt', 'mi', 'mr', 'mh', 'mn',
  'na', 'nv', 'nd', 'ne', 'ng', 'nb', 'nn', 'no', 'ii', 'nr',
  'oc', 'oj', 'cu', 'om', 'or', 'os',
  'pa', 'pi', 'fa', 'pl', 'ps', 'pt',
  'qu',
  'rm', 'rn', 'ro', 'ru',
  'sa', 'sc', 'sd', 'se', 'sm', 'sg', 'sr', 'gd', 'sn', 'si', 'sk', 'sl', 'so', 'st',
  'es', 'su', 'sw', 'ss', 'sv',
  'ta', 'te', 'tg', 'th', 'ti', 'bo', 'tk', 'tl', 'tn', 'to', 'tr', 'ts', 'tt', 'tw', 'ty',
  'ug', 'uk', 'ur', 'uz',
  've', 'vi', 'vo',
  'wa', 'cy', 'wo', 'fy',
  'xh',
  'yi', 'yo',
  'za', 'zu'
])

// ─── Locale suffix detection ───────────────────────────────────────────────────

// Detects locale suffixes from source column names. Handles these formats:
//   description_de       → { base: 'description',  locale: 'de'    }
//   description_de_DE    → { base: 'description',  locale: 'de_DE' }
//   description_en-US    → { base: 'description',  locale: 'en_US' }
//   first_name_fr        → { base: 'first_name',   locale: 'fr'    }
//   price-pt_BR          → { base: 'price',        locale: 'pt_BR' }
//   title.it             → { base: 'title',        locale: 'it'    }
// Normalizes to lowercase lang + optional uppercase region joined by underscore.
function detectLocaleSuffix (s: string): { base: string, locale: string } | null {
  const match = /^(.*?)[_\-\.]([a-z]{2})(?:[_\-]([A-Za-z]{2,4}))?$/.exec(s)
  if (match === null) return null
  const base = match[1]
  const lang = match[2]
  const region = match[3]
  if (base.length === 0) return null
  if (!ISO_639_1_CODES.has(lang)) return null
  const locale = region !== undefined ? `${lang}_${region.toUpperCase()}` : lang
  return { base, locale }
}

// ─── String utilities ─────────────────────────────────────────────────────────

function normalize (s: string): string {
  return s.toLowerCase().replace(/[^a-z0-9]/g, '')
}

function tokenize (s: string): string[] {
  return s
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
    .toLowerCase()
    .split(/[\s_\-\.]+/)
    .filter((t) => t.length > 0)
}

// ─── Similarity metrics ───────────────────────────────────────────────────────

function bigramJaccard (a: string, b: string): number {
  if (a.length === 0 && b.length === 0) return 1
  if (a.length < 2 || b.length < 2) return a === b ? 1 : 0

  const aGrams = new Set<string>()
  const bGrams = new Set<string>()
  for (let i = 0; i < a.length - 1; i++) aGrams.add(a.slice(i, i + 2))
  for (let i = 0; i < b.length - 1; i++) bGrams.add(b.slice(i, i + 2))

  let intersectionSize = 0
  aGrams.forEach((g) => { if (bGrams.has(g)) intersectionSize++ })

  const unionSize = aGrams.size + bGrams.size - intersectionSize
  return unionSize === 0 ? 0 : intersectionSize / unionSize
}

function tokenSetJaccard (a: string[], b: string[]): number {
  if (a.length === 0 && b.length === 0) return 1
  const aSet = new Set(a)
  const bSet = new Set(b)
  let intersectionSize = 0
  aSet.forEach((t) => { if (bSet.has(t)) intersectionSize++ })
  const unionSize = aSet.size + bSet.size - intersectionSize
  return unionSize === 0 ? 0 : intersectionSize / unionSize
}

function matchScore (sourceLabel: string, attr: ClassAttribute): number {
  const sNorm = normalize(sourceLabel)
  const titleNorm = normalize(attr.title)
  const keyNorm = normalize(attr.key)

  // For dotted field-collection paths (e.g. "attributes.Engine.cylinders"),
  // also score against the leaf segment so "cylinders" still matches at 100%.
  const dotIndex = attr.key.lastIndexOf('.')
  const keyLeaf = dotIndex >= 0 ? attr.key.slice(dotIndex + 1) : null
  const keyLeafNorm = keyLeaf !== null ? normalize(keyLeaf) : null

  if (sNorm === titleNorm || sNorm === keyNorm || (keyLeafNorm !== null && sNorm === keyLeafNorm)) return 100

  const sTokens = tokenize(sourceLabel)
  const titleTokens = tokenize(attr.title)
  const keyTokens = tokenize(attr.key)

  let best = Math.max(
    tokenSetJaccard(sTokens, titleTokens),
    tokenSetJaccard(sTokens, keyTokens),
    bigramJaccard(sNorm, titleNorm),
    bigramJaccard(sNorm, keyNorm)
  )

  if (keyLeaf !== null && keyLeafNorm !== null) {
    best = Math.max(best,
      tokenSetJaccard(sTokens, tokenize(keyLeaf)),
      bigramJaccard(sNorm, keyLeafNorm)
    )
  }

  return Math.round(best * 100)
}

// ─── Public API ───────────────────────────────────────────────────────────────

export function computeAutofillSuggestions (
  columnHeaderOptions: Array<{ value: string, label: string }>,
  attributesMap: Record<string, ClassAttribute[]>,
  existingMappings: MappingConfigItem[],
  sourceRows: SourceRow[]
): MappingSuggestion[] {
  const usedIndices = new Set<string>()
  existingMappings.forEach((item) => {
    ;(item.dataSourceIndex ?? []).forEach((idx) => usedIndices.add(idx))
  })

  // Flatten attributes across all transformation-type buckets, deduplicating by
  // attribute key. DEFAULT_ATTR_MAP_KEY is processed first so its entries win
  // when the same field appears in multiple buckets.
  const attrsByKey = new Map<string, ClassAttribute>()
  const keyOrder = [
    DEFAULT_ATTR_MAP_KEY,
    ...Object.keys(attributesMap).filter((k) => k !== DEFAULT_ATTR_MAP_KEY)
  ]
  for (const mapKey of keyOrder) {
    for (const attr of (attributesMap[mapKey] ?? [])) {
      if (!attrsByKey.has(attr.key)) {
        attrsByKey.set(attr.key, attr)
      }
    }
  }
  const attrs = Array.from(attrsByKey.values())

  const suggestions: MappingSuggestion[] = []

  for (const col of columnHeaderOptions) {
    if (usedIndices.has(col.value)) continue

    // Pre-compute locale suffix detection once per column (shared across attrs).
    const detectedLocale = detectLocaleSuffix(col.label)

    // Track full-name match and locale-aware base-name match separately to
    // avoid order-dependent language clearing when a later full match ties.
    let bestFullScore = -1
    let bestFullAttr: ClassAttribute | null = null

    let bestBaseScore = -1
    let bestBaseAttr: ClassAttribute | null = null
    let bestBaseLanguage: string | null = null

    for (const attr of attrs) {
      // Full source label match — works for any attribute type.
      const score = matchScore(col.label, attr)
      if (score > bestFullScore) {
        bestFullScore = score
        bestFullAttr = attr
      }

      // Base name match — only tried for localized attributes and when a locale
      // suffix was detected. Handles description_de → description (lang: de).
      if (attr.localized === true && detectedLocale !== null) {
        const baseScore = matchScore(detectedLocale.base, attr)
        if (baseScore > bestBaseScore) {
          bestBaseScore = baseScore
          bestBaseAttr = attr
          bestBaseLanguage = detectedLocale.locale
        }
      }
    }

    // Prefer the base match when it scores at least as well as the full match
    // so that a localized field with language wins over a bare full-name match.
    const useBase = bestBaseScore >= bestFullScore && bestBaseAttr !== null
    const bestScore = useBase ? bestBaseScore : bestFullScore
    const bestAttr = useBase ? bestBaseAttr : bestFullAttr
    const bestLanguage = useBase ? bestBaseLanguage : null

    if (bestAttr !== null && bestScore >= MIN_SCORE) {
      const previewRow = sourceRows.find((r) => r.dataIndex === col.value)
      suggestions.push({
        id: uuid(),
        sourceIndex: col.value,
        sourceLabel: col.label,
        targetFieldName: bestAttr.key,
        targetFieldLabel: bestAttr.title,
        score: bestScore,
        language: bestLanguage,
        previewResult: previewRow?.value ?? null
      })
    }
  }

  return suggestions.sort((a, b) => b.score - a.score)
}
