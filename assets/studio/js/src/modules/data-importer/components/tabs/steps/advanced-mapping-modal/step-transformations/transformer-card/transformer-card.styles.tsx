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
    transformerCard: css`
      border: 1px solid ${token.colorBorderSecondary};
      border-radius: ${token.borderRadiusSM}px;
      padding: 6px ${token.paddingXS}px;
      display: flex;
      flex-direction: column;
      gap: ${token.paddingXXS}px;
      background: ${token.colorFillAdditional};
    `,

    transformerCardOverlay: css`
      border: 1px solid ${token.colorBorderSecondary};
      border-radius: ${token.borderRadiusSM}px;
      padding: 6px ${token.paddingXS}px;
      display: flex;
      flex-direction: column;
      gap: ${token.paddingXXS}px;
      background: ${token.colorFillAdditional};
      box-shadow: ${token.boxShadowSecondary};
      cursor: grabbing;
    `,

    transformerCardHeader: css`
      display: flex;
      align-items: center;
      gap: ${token.paddingXXS}px;
    `,

    dragHandle: css`
      display: flex;
      align-items: center;
      cursor: grab;
      user-select: none;
      flex-shrink: 0;
      margin-right: ${token.paddingXXS}px;

      &:active {
        cursor: grabbing;
      }
    `,

    dragHandleIcon: css`
      font-size: 14px;
      line-height: 1;
      color: ${token.colorTextTertiary};
    `,

    transformerLabel: css`
      font-weight: 400;
      font-size: 12px;
      color: ${token.colorText};
    `,

    transformerCollapseIcon: css`
      display: flex;
      align-items: center;
      color: ${token.colorText};
      flex-shrink: 0;
    `,

    transformerDeleteButton: css`
      margin-left: auto;
      color: ${token.colorPrimary} !important;

      &:hover {
        color: ${token.colorPrimaryHover} !important;
        background: transparent !important;
      }
    `
  }
})
