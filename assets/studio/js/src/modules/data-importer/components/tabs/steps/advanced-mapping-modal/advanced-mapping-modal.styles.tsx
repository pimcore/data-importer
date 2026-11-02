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
    sectionPanel: css`
      & .ant-collapse.collapse-item--theme-default {
        background-color: ${token.colorFillAlter};

        & .ant-collapse-content {
          background-color: ${token.colorBgContainer};
        }
      }
    `,

    stepBadge: css`
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 10px;
      background-color: ${token.colorPrimary};
      color: ${token.colorTextLightSolid};
      font-size: ${token.fontSizeSM}px;
      font-weight: ${token.fontWeightStrong};
      line-height: 20px;
      margin-inline-end: ${token.marginXXS}px;
    `,

    footer: css`
      border-top: 1px solid ${token.colorBorderSecondary};
      padding-top: ${token.paddingSM}px;
      margin-top: ${token.paddingXXS}px;
    `
  }
})
