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
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { type MappingField, type MappingRow } from './mapping-state'
import {
  cellText,
  firstValueRow,
  type TransformationAnswer,
  type TransformationSource,
  useTransformationPreview
} from './transformations-editor'

const CONVERSION = 'data-importer.ad-hoc-import.transformations.conversion-short'

const useStyles = createStyles(({ token, css }) => ({
  cell: css`
    display: flex;
    flex-direction: column;
    min-width: 0;
  `,
  value: css`
    overflow: hidden;
    font-weight: ${token.fontWeightStrong};
    white-space: nowrap;
    text-overflow: ellipsis;
  `,
  failed: css`
    color: ${token.colorError};
  `,
  conversion: css`
    font-size: ${token.fontSizeSM}px;
    line-height: 16px;
    color: ${token.colorTextTertiary};
  `
}))

export interface StoredValue {
  text: string
  failed: boolean
  // the conversion into the field's type, by its operator; null when the value fits as it is
  conversion: string | null
}

// the column's first value as it goes into the field, from the server's answer to its steps
export const storedValueOf = (t: ReturnType<typeof useTranslation>['t'], answer: TransformationAnswer | null): StoredValue | null => {
  if (answer === null) {
    return null
  }
  const result = answer.results[firstValueRow(answer)]

  return {
    text: cellText(t, result),
    failed: !answer.fits || (result?.failed ?? false),
    conversion: answer.conversion
  }
}

export interface StoredAsProps {
  row: MappingRow
  field: MappingField | undefined
  // what the transformations run on; none, nothing is shown
  source: TransformationSource | null
}

// what the column's first value is stored as in its field, after its transformations and the conversion
export const StoredAs = ({ row, field, source }: StoredAsProps): React.JSX.Element | null => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  // a classification store key has no type the server knows here, and the match column goes into no field
  const runs = field !== undefined && !row.isMatch && row.choice?.key === undefined
  const { answer, isLoading } = useTransformationPreview({ row, field: runs ? field : undefined, source, steps: row.transformations })
  const stored = isLoading ? null : storedValueOf(t, answer)

  if (stored === null) {
    return null
  }

  return (
    <span className={ styles.cell }>
      <span className={ stored.failed ? `${styles.value} ${styles.failed}` : styles.value }>{ stored.text }</span>
      { stored.conversion !== null && <span className={ styles.conversion }>{ t(`${CONVERSION}.${stored.conversion}`) }</span> }
    </span>
  )
}
