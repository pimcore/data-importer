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
    row: css`
      display: flex;
      align-items: center;
      gap: ${token.paddingXS}px;
      cursor: pointer;
      height: 38px;
      padding: ${token.paddingXXS}px ${token.paddingSM}px;
      user-select: none;
    `,

    rowWithBorder: css`
      border-bottom: 1px solid ${token.colorBorderSecondary};
    `,

    badge: css`
      width: 24px;
      height: 24px;
      border-radius: 32px;
      background: ${token.colorPrimary};
      color: ${token.colorTextLightSolid};
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
      flex-shrink: 0;
    `,

    title: css`
      font-size: 12px;
      color: ${token.colorText};
      flex: 1;
    `
  }
})
