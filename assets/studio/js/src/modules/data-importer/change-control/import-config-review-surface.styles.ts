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
  // the shell renders the review full-bleed; the breathing room is this surface's to add
  layout: css`
    display: flex;
    align-items: stretch;
    gap: ${token.marginLG}px;
    height: 70vh;
    min-height: 420px;
    padding: 0 ${token.paddingLG}px;
  `,
  rail: css`
    width: 310px;
    flex-shrink: 0;
    overflow: auto;
    padding: ${token.paddingSM}px;
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: ${token.borderRadiusLG}px;
    background: ${token.colorFillQuaternary};
    display: flex;
    flex-direction: column;
    gap: ${token.marginXXS}px;
  `,
  railHead: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    font-size: ${token.fontSizeSM}px;
    text-transform: uppercase;
    letter-spacing: .07em;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorTextTertiary};
    padding: ${token.paddingXXS}px ${token.paddingXS}px ${token.paddingXS}px;
  `,
  count: css`
    font-variant-numeric: tabular-nums;
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextTertiary};
    background: ${token.colorBgContainer};
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: 100px;
    padding: 0 ${token.paddingXS}px;
    letter-spacing: 0;
  `,
  section: css`
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding-bottom: ${token.marginXXS}px;
  `,
  sectionHead: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    padding: ${token.paddingXXS}px ${token.paddingXS}px;
  `,
  sectionButton: css`
    flex: 1;
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    border: none;
    background: none;
    padding: 0;
    cursor: pointer;
    color: ${token.colorText};
    text-align: left;
  `,
  sectionSpacer: css`
    width: 16px;
    flex-shrink: 0;
  `,
  caret: css`
    color: ${token.colorTextTertiary};
    font-size: ${token.fontSizeSM}px;
    width: 10px;
  `,
  sectionLabel: css`
    flex: 1;
    font-weight: ${token.fontWeightStrong};
  `,
  change: css`
    display: flex;
    gap: ${token.marginXS}px;
    align-items: flex-start;
    padding: ${token.paddingXXS}px ${token.paddingXS}px ${token.paddingXXS}px ${token.paddingLG}px;
    border-radius: ${token.borderRadius}px;

    &:hover { background: ${token.colorBgContainer}; }
  `,
  mapping: css`
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: ${token.paddingXXS}px ${token.paddingXS}px ${token.paddingXXS}px ${token.paddingLG}px;
  `,
  changeBody: css`
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
  `,
  changeHead: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXXS}px;
    min-width: 0;
  `,
  changeLabel: css`
    font-weight: ${token.fontWeightStrong};
    word-break: break-word;
  `,
  changeValues: css`
    display: flex;
    gap: ${token.marginXXS}px;
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextSecondary};
    min-width: 0;

    > span {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  `,
  was: css`
    text-decoration: line-through;
    color: ${token.colorTextTertiary};
    max-width: 45%;
  `,
  now: css`
    color: ${token.colorText};
  `,
  note: css`
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextTertiary};
    padding: ${token.paddingXS}px ${token.paddingXS}px ${token.paddingXS}px ${token.paddingLG}px;
  `,
  state: css`
    padding: ${token.paddingSM}px;
    color: ${token.colorTextSecondary};
  `,
  newConfig: css`
    display: flex;
    flex-direction: column;
    gap: ${token.marginXXS}px;
  `,
  newName: css`
    font-size: ${token.fontSizeLG}px;
    font-weight: ${token.fontWeightStrong};
    padding: 0 ${token.paddingXS}px;
    word-break: break-word;
  `,
  newCounts: css`
    display: flex;
    gap: ${token.marginXXS}px;
    color: ${token.colorTextSecondary};
    padding: 0 ${token.paddingXS}px ${token.paddingXS}px;
    font-variant-numeric: tabular-nums;
  `,
  editor: css`
    flex: 1;
    min-width: 0;
    overflow: auto;
  `
}))
