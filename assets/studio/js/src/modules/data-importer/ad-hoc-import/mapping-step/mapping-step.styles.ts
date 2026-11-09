/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { createStyles } from '@pimcore/studio-ui-bundle/app'
import { ROW_COLUMNS } from './mapping-row.styles'

const BAR_WIDTH = 220

export const useStyles = createStyles(({ token, css }) => ({
  summary: css`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: ${token.marginSM}px;
    padding: ${token.paddingSM}px ${token.paddingLG}px;
    margin: 0 -${token.paddingLG}px;
    border-block: 1px solid ${token.colorBorderSecondary};
    background: ${token.colorFillAdditional};
  `,
  summaryCount: css`
    display: flex;
    flex-direction: column;
    min-width: ${BAR_WIDTH}px;
    font-weight: ${token.fontWeightStrong};
  `,
  bar: css`
    display: flex;
    width: ${BAR_WIDTH}px;
    height: 6px;
    overflow: hidden;
    border-radius: 3px;
    background: ${token.colorBorderSecondary};
  `,
  barMapped: css`
    background: ${token.colorSuccess};
  `,
  barSkipped: css`
    background: ${token.colorTextDisabled};
  `,
  spacer: css`
    flex-grow: 1;
  `,
  note: css`
    display: flex;
    align-items: center;
    gap: ${token.marginSM}px;
    padding: ${token.paddingXS}px ${token.paddingLG}px;
    margin: 0 -${token.paddingLG}px;
    border-bottom: 1px solid ${token.colorBorderSecondary};
  `,
  noteIcon: css`
    color: ${token.colorPrimary};
  `,
  table: css`
    max-height: calc(100vh - 500px);
    min-height: 120px;
    overflow: auto;
  `,
  rows: css`
    margin: 0;
    padding: 0;
    list-style: none;
  `,
  head: css`
    display: grid;
    grid-template-columns: ${ROW_COLUMNS};
    position: sticky;
    top: 0;
    z-index: 1;
    border-bottom: 1px solid ${token.colorBorderSecondary};
    background: ${token.Table.headerBg};
    font-weight: ${token.fontWeightStrong};
  `,
  headCell: css`
    padding: ${token.paddingXXS}px;
  `
}))
