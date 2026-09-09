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

export const useStyles = createStyles(({ token, css }) => ({
  layout: css`
    display: flex;
    align-items: stretch;
    height: 70vh;
    min-height: 420px;
  `,
  rail: css`
    width: 320px;
    flex-shrink: 0;
    overflow: auto;
    padding: ${token.paddingSM}px;
    border-right: 1px solid ${token.colorBorderSecondary};
    background: ${token.colorFillQuaternary};
    display: flex;
    flex-direction: column;
    gap: ${token.marginXXS}px;
  `,
  railTitle: css`
    font-size: ${token.fontSizeSM}px;
    text-transform: uppercase;
    letter-spacing: .07em;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorTextTertiary};
    padding: ${token.paddingXS}px ${token.paddingXXS}px ${token.paddingXXS}px;
  `,
  change: css`
    display: flex;
    gap: ${token.marginXS}px;
    align-items: flex-start;
    padding: ${token.paddingXS}px;
    border-radius: ${token.borderRadius}px;
    cursor: pointer;

    &:hover { background: ${token.colorBgContainer}; }
  `,
  mapping: css`
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: ${token.paddingXS}px ${token.paddingXS}px ${token.paddingXS}px ${token.paddingLG}px;
  `,
  changeBody: css`
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
  `,
  changeHead: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXXS}px;
  `,
  changeLabel: css`
    font-weight: ${token.fontWeightStrong};
    word-break: break-word;
  `,
  changeValues: css`
    display: flex;
    flex-wrap: wrap;
    gap: ${token.marginXXS}px;
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextSecondary};
  `,
  was: css`
    text-decoration: line-through;
    color: ${token.colorTextTertiary};
  `,
  now: css`
    color: ${token.colorText};
  `,
  address: css`
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextTertiary};
    word-break: break-all;
  `,
  note: css`
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextTertiary};
    padding: ${token.paddingXS}px;
  `,
  state: css`
    padding: ${token.paddingSM}px;
    color: ${token.colorTextSecondary};
  `,
  editor: css`
    flex: 1;
    min-width: 0;
    overflow: auto;
  `
}))
