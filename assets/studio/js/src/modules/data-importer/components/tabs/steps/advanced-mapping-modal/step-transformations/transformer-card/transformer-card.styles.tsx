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
    transformerCardWrapper: css`
      & .ant-collapse.collapse-item--theme-primary {
        background-color: ${token.colorFillAdditional};
      }

      & .ant-collapse-content {
        background-color: ${token.colorFillAdditional} !important;
      }
    `,

    dragHandle: css`
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

    transformerDeleteButton: css`
      color: ${token.colorPrimary} !important;

      &:hover {
        color: ${token.colorPrimaryHover} !important;
        background: transparent !important;
      }
    `
  }
})
