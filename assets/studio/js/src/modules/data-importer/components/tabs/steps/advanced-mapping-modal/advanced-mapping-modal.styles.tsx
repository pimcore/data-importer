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
    modalBody: css`
      display: flex;
      flex-direction: column;
      gap: ${token.paddingSM}px;
    `,

    sectionWrapper: css`
      border: 1px solid ${token.colorBorderSecondary};
      border-radius: ${token.borderRadiusLG}px;
      overflow: hidden;
      background: ${token.colorBgContainer};
    `,

    sectionWrapperCollapsed: css`
      background: ${token.colorFillAlter};
    `,

    sectionBody: css`
      padding: ${token.paddingSM}px;
      border-top: 1px solid ${token.colorBorderSecondary};
    `,

    sectionBodyHidden: css`
      display: none;
    `,

    footer: css`
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: ${token.paddingXS}px;
      border-top: 1px solid ${token.colorBorderSecondary};
      padding-top: ${token.paddingSM}px;
      margin-top: ${token.paddingXXS}px;
    `
  }
})
