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

function detectLocaleSuffix (s: string, validLanguages: Set<string>): { base: string, locale: string } | null {
  const match = /^(.*?)[_.-]([a-z]{2})(?:[_-]([A-Za-z]{2,4}))?$/.exec(s)
  if (match === null) return null
  const base = match[1]
  const lang = match[2]
  const region = match[3]
  if (base.length === 0) return null
  if (!validLanguages.has(lang)) return null
  const locale = region !== undefined ? `${lang}_${region.toUpperCase()}` : lang
  return { base, locale }
}

function normalize (s: string): string {
  return s.toLowerCase().replace(/[^a-z0-9]/g, '')
}

function tokenize (s: string): string[] {
  return s
    .split(/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])|[\s_.-]+/)
    .map((t) => t.toLowerCase())
    .filter((t) => t.length > 0)
}

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

function buildUsedIndices (existingMappings: MappingConfigItem[]): Set<string> {
  const used = new Set<string>()
  existingMappings.forEach((item) => {
    ;(item.dataSourceIndex ?? []).forEach((idx) => used.add(idx))
  })
  return used
}

function flattenAttributes (attributesMap: Record<string, ClassAttribute[]>): ClassAttribute[] {
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
  return Array.from(attrsByKey.values())
}

interface BestMatch {
  attr: ClassAttribute
  score: number
  language: string | null
}

// Track full-name match and locale-aware base-name match separately to
// avoid order-dependent language clearing when a later full match ties.
function findBestMatch (label: string, attrs: ClassAttribute[], validLanguages: Set<string>): BestMatch | null {
  const detectedLocale = detectLocaleSuffix(label, validLanguages)
  let bestFullScore = -1
  let bestFullAttr: ClassAttribute | null = null
  let bestBaseScore = -1
  let bestBaseAttr: ClassAttribute | null = null
  let bestBaseLanguage: string | null = null

  for (const attr of attrs) {
    const score = matchScore(label, attr)
    if (score > bestFullScore) {
      bestFullScore = score
      bestFullAttr = attr
    }
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
  const attr = useBase ? bestBaseAttr! : bestFullAttr
  const score = useBase ? bestBaseScore : bestFullScore
  const language = useBase ? bestBaseLanguage : null

  if (attr === null || score < MIN_SCORE) return null
  return { attr, score, language }
}

export function computeAutofillSuggestions (
  columnHeaderOptions: Array<{ value: string, label: string }>,
  attributesMap: Record<string, ClassAttribute[]>,
  existingMappings: MappingConfigItem[],
  sourceRows: SourceRow[],
  validLanguages: string[]
): MappingSuggestion[] {
  const usedIndices = buildUsedIndices(existingMappings)
  const attrs = flattenAttributes(attributesMap)
  const validLanguagesSet = new Set(validLanguages.map((l) => l.toLowerCase()))
  const suggestions: MappingSuggestion[] = []

  for (const col of columnHeaderOptions) {
    if (usedIndices.has(col.value)) continue

    const best = findBestMatch(col.label, attrs, validLanguagesSet)
    if (best === null) continue

    const previewRow = sourceRows.find((r) => r.dataIndex === col.value)
    suggestions.push({
      id: uuid(),
      sourceIndex: col.value,
      sourceLabel: col.label,
      targetFieldName: best.attr.key,
      targetFieldLabel: best.attr.title,
      score: best.score,
      language: best.language,
      previewResult: previewRow?.value ?? null
    })
  }

  return suggestions.sort((a, b) => b.score - a.score)
}
