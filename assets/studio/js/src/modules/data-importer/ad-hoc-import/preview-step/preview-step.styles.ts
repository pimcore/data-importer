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

export const ROW_ERROR_MARK = 'ad-hoc-import-row-error'
export const ROW_WARNING_MARK = 'ad-hoc-import-row-warning'

export const useStyles = createStyles(({ token, css }) => ({
  counts: css`
    display: flex;
    gap: ${token.marginSM}px;
    margin-bottom: ${token.margin}px;
  `,
  count: css`
    flex: 1 1 0;
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: ${token.borderRadiusLG}px;
    padding: ${token.paddingXS}px ${token.paddingSM}px;
    display: flex;
    flex-direction: column;
  `,
  countWithErrors: css`
    border-color: ${token.colorErrorBorder};
    background: ${token.colorErrorBg};
  `,
  number: css`
    font-size: ${token.fontSizeHeading4}px;
    line-height: ${token.lineHeightHeading4};
    font-weight: ${token.fontWeightStrong};
  `,
  label: css`
    color: ${token.colorTextSecondary};
  `,
  checked: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    margin-bottom: ${token.marginXS}px;
  `,
  checkedTitle: css`
    font-weight: ${token.fontWeightStrong};
  `,
  hint: css`
    color: ${token.colorTextTertiary};
    flex-grow: 1;
  `,
  // a cell of one's own loses the grid's padding and its vertical centering
  cell: css`
    display: flex;
    align-items: center;
    width: 100%;
    height: 100%;
    padding: ${token.paddingXXS}px ${token.paddingXS}px;
    white-space: normal;
    overflow-wrap: anywhere;
  `,
  matchValue: css`
    font-weight: ${token.fontWeightStrong};
  `,
  error: css`
    color: ${token.colorErrorText};
  `,
  warning: css`
    color: ${token.colorWarningText};
  `,
  // Studio's grid takes no class per row, a row is tinted through what its cells hold
  grid: css`
    tr:has(.${ROW_ERROR_MARK}) > td {
      background: ${token.colorErrorBg};
    }

    tr:has(.${ROW_WARNING_MARK}) > td {
      background: ${token.colorWarningBg};
    }
  `,
  pagination: css`
    margin-top: ${token.marginXS}px;
  `
}))
