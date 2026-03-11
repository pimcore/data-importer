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
    wrapper: css`
      display: flex;
      flex-direction: column;
      gap: ${token.paddingXS}px;
      flex: 1;
    `,

    header: css`
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 32px;
    `,

    title: css`
      font-size: 12px;
      font-weight: 600;
      color: ${token.colorPrimaryText};
    `,

    buttonGroup: css`
      display: flex;
      gap: ${token.paddingXXS}px;
    `,

    /* ── import mode ── */

    tableWrapper: css`
      min-height: 180px;
      max-height: 420px;
      border: 0.5px solid ${token.colorBorderSecondary};
      border-radius: ${token.borderRadiusSM}px;
      overflow: hidden;
      overflow-y: auto;
    `,

    tableHeader: css`
      display: grid;
      grid-template-columns: 1fr 1fr;
      background: ${token.colorFillAlter};
      border-bottom: 0.5px solid ${token.colorBorderSecondary};
    `,

    tableHeaderCell: css`
      font-size: 12px;
      padding: 6px ${token.paddingXS}px;
      font-weight: 500;
    `,

    tableHeaderCellBorder: css`
      border-right: 0.5px solid ${token.colorBorderSecondary};
    `,

    tableRow: css`
      display: grid;
      grid-template-columns: 1fr 1fr;
      background: ${token.colorBgContainer};
      height: 32px;
    `,

    tableRowHighlighted: css`
      background: ${token.colorSuccessBg};
    `,

    tableRowBorder: css`
      border-bottom: 0.5px solid ${token.colorBorderSecondary};
    `,

    tableCell: css`
      font-size: 12px;
      padding: 0 ${token.paddingXS}px;
      display: flex;
      align-items: center;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    `,

    tableCellBorder: css`
      border-right: 0.5px solid ${token.colorBorderSecondary};
    `,

    stateMessage: css`
      padding: ${token.paddingMD}px ${token.paddingXS}px;
      font-size: 12px;
      color: ${token.colorTextSecondary};
      text-align: center;
    `,

    /* ── result mode ── */

    previewArea: css`
      min-height: 100px;
      overflow-y: auto;
    `,

    muted: css`
      font-size: 12px;
      color: ${token.colorTextQuaternary};
    `,

    previewLine: css`
      font-size: 12px;
      color: ${token.colorTextSecondary};
      margin-bottom: ${token.paddingXXS}px;
    `
  }
})
