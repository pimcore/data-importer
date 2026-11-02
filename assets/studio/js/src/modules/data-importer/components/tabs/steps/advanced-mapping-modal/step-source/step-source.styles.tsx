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
    twoColumnLayout: css`
      min-height: 280px;
      width: 100%;
      overflow: hidden;
    `,

    leftColumn: css`
      flex: 1 1 0;
      min-width: 0;
      background: ${token.colorFillAdditional};
      border-radius: ${token.borderRadius}px;
      padding: 10px ${token.paddingSM}px;
    `,

    labelSmall: css`
      font-size: 12px;
    `,

    selectFull: css`
      width: 100%;
      /* Ant Design sets max-width: 9999px as an inline style on .ant-select
         for multiple-mode selects — override it so the select cannot overflow
         its flex container. */
      &.ant-select {
        max-width: 100% !important;
      }
    `,

    rightColumn: css`
      flex: 1 1 0;
      min-width: 0;
    `
  }
})
