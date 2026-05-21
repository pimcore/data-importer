/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { createStyles } from 'antd-style'

export const useStyles = createStyles(({ css, token }) => {
  return {
    tableHeaderRow: css`
      padding: ${token.paddingXXS}px ${token.paddingMD}px;
      border-bottom: 1px solid ${token.colorBorderSecondary};
    `,

    tableHeaderCell: css`
      color: ${token.colorTextSecondary};
      white-space: nowrap;
    `,

    tableRow: css`
      padding: ${token.paddingXXS}px ${token.paddingMD}px;
      min-height: 36px;

      &:hover {
        background: ${token.colorFillAlter};
        cursor: pointer;
      }
    `,

    sourceCell: css`
      flex: 1.5;
      min-width: 0;
      overflow: hidden;
    `,

    destinationCell: css`
      flex: 2.5;
      min-width: 0;
      overflow: hidden;
    `,

    localeTag: css`
      flex-shrink: 0;
      font-size: ${token.fontSizeSM}px;
      background-color: ${token.colorPrimaryBg} !important;
      color: ${token.colorPrimary} !important;
      border-color: ${token.colorPrimaryBorder} !important;
    `,

    resultCell: css`
      flex: 2;
      min-width: 0;
      color: ${token.colorTextDescription};
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    `,

    cellText: css`
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    `,

    emptyState: css`
      padding: ${token.paddingXL}px ${token.paddingMD}px;
    `,

    scoreText: css`
      white-space: nowrap;
    `,

    dotGreen: css`
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background-color: ${token.colorSuccess};
      flex-shrink: 0;
    `,

    dotYellow: css`
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background-color: ${token.colorWarning};
      flex-shrink: 0;
    `,

    dotOrange: css`
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background-color: ${token.colorWarningTextActive};
      flex-shrink: 0;
    `
  }
})
