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
import { BOLD } from '../dialog-layout'

export const useStyles = createStyles(({ token, css }) => ({
  card: css`
    max-width: 560px;
    box-sizing: border-box;
    border: 1px solid ${token.colorBorderContainer};
    border-radius: ${token.borderRadiusLG}px;
    padding: ${token.paddingSM}px ${token.paddingSM}px ${token.paddingSM}px ${token.padding}px;
    display: flex;
    align-items: center;
    gap: ${token.marginSM}px;
  `,
  cardRefused: css`
    border-color: ${token.colorErrorBorder};
  `,
  badge: css`
    height: 32px;
    width: 40px;
    flex-shrink: 0;
    border-radius: ${token.borderRadius}px;
    background: ${token.colorPrimaryBg};
    color: ${token.colorPrimary};
    font-weight: ${BOLD};
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
  `,
  // colorErrorText reads 3:1 on colorErrorBg, too little for the badge's small text
  badgeRefused: css`
    background: ${token.colorErrorBg};
    color: ${token.red7};
  `,
  text: css`
    flex-grow: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
  `,
  name: css`
    font-weight: ${BOLD};
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  `,
  detail: css`
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextDescription};
  `,
  progress: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    padding-top: ${token.paddingXXS}px;

    .ant-progress {
      width: 260px;
      margin: 0;
    }
  `,
  // antd draws a normal bar in colorInfo; the canvas draws it in the primary colour
  barColor: token.colorPrimary
}))
