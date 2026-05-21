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
    panel: css`
      border: 1px solid ${token.colorBorderSecondary};
      border-radius: ${token.borderRadiusLG}px;
      background: ${token.colorBgContainer};
      margin-bottom: ${token.paddingSM}px;
      overflow: hidden;
      flex-shrink: 0;
    `,

    header: css`
      padding: ${token.paddingXS}px ${token.paddingMD}px;
      background: ${token.colorFillTertiary};
      border-bottom: 1px solid ${token.colorBorderSecondary};
      cursor: pointer;
      user-select: none;
    `,

    title: css`
      font-weight: 600;
      color: ${token.colorPrimary};
      font-size: ${token.fontSize}px;
    `,

    tableHeaderRow: css`
      padding: ${token.paddingXXS}px ${token.paddingMD}px;
      border-bottom: 1px solid ${token.colorBorderSecondary};
    `,

    tableHeaderCell: css`
      font-size: ${token.fontSize}px;
      color: ${token.colorTextSecondary};
      font-weight: 600;
      white-space: nowrap;
    `,

    tableRow: css`
      padding: ${token.paddingXXS}px ${token.paddingMD}px;
      border-bottom: 1px solid ${token.colorFillSecondary};
      min-height: 36px;

      &:last-child {
        border-bottom: none;
      }

      &:hover {
        background: ${token.colorFillAlter};
        cursor: pointer;
      }
    `,

    checkboxCell: css`
      width: 24px;
      flex-shrink: 0;
    `,

    scoreCell: css`
      width: 56px;
      flex-shrink: 0;
    `,

    sourceCell: css`
      flex: 1.5;
      min-width: 0;
      overflow: hidden;
    `,

    arrowCell: css`
      width: 36px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    `,

    destinationCell: css`
      flex: 2.5;
      min-width: 0;
      overflow: hidden;
      display: flex;
      align-items: center;
      gap: ${token.paddingXXS}px;
    `,

    localeTag: css`
      flex-shrink: 0;
      font-size: ${token.fontSizeSM}px;
      background-color: ${token.colorPrimaryBg} !important;
      color: ${token.colorPrimary} !important;
      border-color: ${token.colorPrimaryBorder} !important;
      font-weight: 600;
    `,

    resultCell: css`
      flex: 2;
      min-width: 0;
      color: ${token.colorTextDescription};
      font-size: ${token.fontSize}px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    `,

    cellText: css`
      font-size: ${token.fontSize}px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    `,

    footer: css`
      padding: ${token.paddingXS}px ${token.paddingMD}px;
      border-top: 1px solid ${token.colorBorderSecondary};
      background: ${token.colorFillAlter};
    `,

    selectedCount: css`
      font-size: ${token.fontSizeSM}px;
      color: ${token.colorTextSecondary};
    `,

    emptyState: css`
      padding: ${token.paddingMD}px;
      color: ${token.colorTextSecondary};
      text-align: center;
      font-size: ${token.fontSizeSM}px;
    `,

    scoreText: css`
      font-size: ${token.fontSize}px;
      font-weight: 600;
      color: ${token.colorText};
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
