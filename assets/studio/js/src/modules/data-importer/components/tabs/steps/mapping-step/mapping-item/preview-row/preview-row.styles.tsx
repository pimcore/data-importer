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

export const useStyles = createStyles(({ css, token }) => ({
  // draws the sides and bottom of the box the panel opens (panelDndWrapper); its top border is the divider;
  // a fieldset (the native group), so its own margin and min-content width are reset
  row: css`
    display: flex;
    min-width: 0;
    margin: 0;
    align-items: center;
    gap: ${token.marginSM}px;
    min-height: 48px;
    padding: ${token.paddingSM}px ${token.paddingMD}px;
    background-color: ${token.colorBgContainer};
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: 0 0 ${token.borderRadiusLG}px ${token.borderRadiusLG}px;
    transition: border-color 0.15s;
  `,

  // follows the expanded panel's highlight; the divider stays light
  rowExpanded: css`
    border-color: ${token.colorPrimaryBorder};
    border-top-color: ${token.colorBorderSecondary};
  `,

  label: css`
    flex-shrink: 0;
    color: ${token.colorText};
  `,

  value: css`
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: ${token.colorText};
  `,

  muted: css`
    color: ${token.colorTextDescription};
  `,

  warning: css`
    color: ${token.colorWarningText};
  `
}))
