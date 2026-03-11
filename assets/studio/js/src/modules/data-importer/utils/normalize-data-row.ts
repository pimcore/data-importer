/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

export interface DataRow {
  dataIndex: string
  label: string
  value: string
}

/**
 * Normalizes a raw data-preview row returned by the backend into a flat
 * `DataRow` shape suitable for display in preview panels and the mapping step.
 *
 * The backend may return `data` as a string or an arbitrary JSON value;
 * non-string values are stringified for display.
 */
export function normalizeDataRow (raw: Record<string, any>): DataRow {
  return {
    dataIndex: String(raw.dataIndex ?? ''),
    label: String(raw.label ?? raw.dataIndex ?? ''),
    value: typeof raw.data === 'string' ? raw.data : (raw.data !== undefined ? JSON.stringify(raw.data) : '')
  }
}
