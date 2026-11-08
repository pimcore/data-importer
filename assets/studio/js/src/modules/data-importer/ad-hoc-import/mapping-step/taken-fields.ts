/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type FieldChoice, type MappingField, type MappingRow } from './mapping-state'

const sameChoice = (a: FieldChoice, b: FieldChoice): boolean =>
  a.field === b.field && a.language === b.language && a.key?.id === b.key?.id

export interface TakenFields {
  // whether another column than this one already goes into the field
  has: (choice: FieldChoice, column?: string) => boolean
}

const choiceKey = ({ field, language, key }: FieldChoice): string => JSON.stringify([field, language, key?.id ?? null])

/**
 * Which columns go into which field, built once per mapping so that each field option is looked up, not searched.
 */
export const takenFields = (rows: MappingRow[]): TakenFields => {
  const columnsOf = new Map<string, string[]>()
  for (const row of rows) {
    if (!row.skipped && row.choice !== undefined) {
      const key = choiceKey(row.choice)
      columnsOf.set(key, [...(columnsOf.get(key) ?? []), row.column])
    }
  }

  return {
    has: (choice, column) => (columnsOf.get(choiceKey(choice)) ?? []).some((other) => other !== column)
  }
}

/**
 * Whether another column than this one already goes into the field; one column per field, and per language for a
 * localized one.
 */
export const isTaken = (rows: MappingRow[], choice: FieldChoice, column?: string): boolean =>
  rows.some((row) => row.column !== column && !row.skipped && row.choice !== undefined && sameChoice(row.choice, choice))

/**
 * Whether the column can still go into the field: a classification store by another key, a localized field in another
 * language.
 */
export const canGoInto = (taken: TakenFields, field: MappingField, column: string, languages: string[]): boolean => {
  if (field.classificationStore) {
    return true
  }
  if (field.localized) {
    return languages.length === 0 || languages.some((language) => !taken.has({ field: field.key, language }, column))
  }

  return !taken.has({ field: field.key, language: null }, column)
}
