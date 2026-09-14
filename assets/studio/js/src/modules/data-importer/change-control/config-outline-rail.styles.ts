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

/** The create summary owns its styles: it is one card, and the rail around it is not its business. */
export const useStyles = createStyles(({ token, css }) => ({
  card: css`
    background: ${token.colorBgContainer};
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: ${token.borderRadiusLG}px;
    box-shadow: ${token.boxShadowTertiary};
    margin-right: ${token.margin}px;
  `,
  head: css`
    display: flex;
    flex-direction: column;
    gap: ${token.marginXXS}px;
    padding: ${token.padding}px ${token.padding}px ${token.paddingSM}px;
    border-bottom: 1px solid ${token.colorSplit};
  `,
  name: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
  `,
  nameText: css`
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorText};
  `,
  /* the on/off switch of the whole pipeline, said once and quietly */
  pill: css`
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: ${token.marginXXS}px;
    height: ${token.controlHeightSM - 4}px;
    padding: 0 ${token.paddingXS}px;
    border-radius: ${token.borderRadiusSM}px;
    background: ${token.colorFillTertiary};
    color: ${token.colorTextSecondary};
    font-size: ${token.fontSizeSM - 1}px;
    font-weight: ${token.fontWeightStrong};
    line-height: 1;
  `,
  dot: css`
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: ${token.colorTextQuaternary};
  `,
  dotOn: css`
    background: ${token.colorSuccess};
  `,
  description: css`
    font-size: ${token.fontSizeSM}px;
    line-height: ${token.lineHeightSM};
    color: ${token.colorTextSecondary};
    overflow-wrap: anywhere;
  `,
  /* read, map, write, run — the marks in one column, the words in the other */
  flow: css`
    display: grid;
    grid-template-columns: ${token.controlHeightSM}px minmax(0, 1fr);
    column-gap: ${token.marginSM}px;
    padding: ${token.padding}px;
  `,
  mark: css`
    display: flex;
    flex-direction: column;
    align-items: center;
  `,
  badge: css`
    flex: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: ${token.controlHeightSM}px;
    height: ${token.controlHeightSM}px;
    border-radius: 50%;
    background: ${token.colorPrimaryBg};
    color: ${token.colorPrimary};
  `,
  line: css`
    flex: 1;
    width: 1px;
    margin: ${token.marginXXS}px 0;
    background: ${token.colorPrimaryBorder};
  `,
  stop: css`
    min-width: 0;
    padding-bottom: ${token.padding}px;
  `,
  stopLast: css`
    min-width: 0;
  `,
  role: css`
    font-size: ${token.fontSizeSM - 2}px;
    font-weight: ${token.fontWeightStrong};
    letter-spacing: .07em;
    text-transform: uppercase;
    color: ${token.colorTextTertiary};
    line-height: ${token.lineHeightSM};
  `,
  value: css`
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorText};
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
  `,
  note: css`
    font-size: ${token.fontSizeSM - 1}px;
    line-height: ${token.lineHeightSM};
    color: ${token.colorTextSecondary};
    overflow-wrap: anywhere;
  `,
  foot: css`
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: ${token.paddingSM}px ${token.padding}px;
    border-top: 1px solid ${token.colorSplit};
    font-variant-numeric: tabular-nums;
  `,
  footLead: css`
    font-size: ${token.fontSizeSM}px;
    line-height: ${token.lineHeightSM};
    color: ${token.colorTextSecondary};
  `,
  footGroups: css`
    font-size: ${token.fontSizeSM - 1}px;
    line-height: ${token.lineHeightSM};
    color: ${token.colorTextTertiary};
  `
}))
