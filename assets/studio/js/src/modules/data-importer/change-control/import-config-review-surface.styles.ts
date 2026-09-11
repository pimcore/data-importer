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
  /* the modal keeps its own gutters; the surface fills the width between them */
  layout: css`
    display: flex;
    align-items: stretch;
    gap: ${token.marginLG}px;
    height: 70vh;
    min-height: 420px;
  `,
  rail: css`
    width: 288px;
    flex: 0 0 288px;
    min-height: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid ${token.colorBorderSecondary};
  `,
  list: css`
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: ${token.padding}px;
    display: flex;
    flex-direction: column;
    gap: ${token.marginSM}px;
  `,
  caption: css`
    padding: ${token.paddingXS}px 0;
    font-size: ${token.fontSizeSM}px;
    font-weight: ${token.fontWeightStrong};
    letter-spacing: .08em;
    text-transform: uppercase;
    color: ${token.colorTextTertiary};
  `,
  history: css`
    display: flex;
    flex-direction: column;
    gap: ${token.marginXXS}px;
    padding: ${token.paddingXS}px ${token.padding}px ${token.paddingSM}px 0;
    border-bottom: 1px solid ${token.colorBorderSecondary};
    margin-bottom: ${token.marginXS}px;
  `,
  historyState: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    color: ${token.colorTextSecondary};
  `,
  group: css`
    display: flex;
    flex-direction: column;
  `,
  groupHead: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    min-height: ${token.controlHeight}px;
  `,
  groupLabel: css`
    flex: 1;
    min-width: 0;
    border: none;
    background: none;
    padding: 0;
    margin: 0;
    font: inherit;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorText};
    text-align: left;
    cursor: pointer;
  `,
  groupLabelActive: css`
    flex: 1;
    min-width: 0;
    border: none;
    background: none;
    padding: 0;
    margin: 0;
    font: inherit;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorPrimary};
    text-align: left;
    cursor: pointer;
  `,
  count: css`
    color: ${token.colorTextTertiary};
    font-size: ${token.fontSizeSM}px;
    font-variant-numeric: tabular-nums;
  `,
  sectionLabel: css`
    padding: ${token.paddingXXS}px 0 0 ${token.paddingLG}px;
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextTertiary};
  `,
  row: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    min-height: ${token.controlHeightSM}px;
    padding-left: ${token.paddingLG}px;
    border-radius: ${token.borderRadiusSM}px;
  `,
  rowTarget: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    min-height: ${token.controlHeightSM}px;
    padding-left: ${token.paddingLG}px;
    border-radius: ${token.borderRadiusSM}px;
    background: ${token.colorPrimaryBg};
  `,
  rowLabel: css`
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    border: none;
    background: none;
    padding: 0;
    margin: 0;
    font: inherit;
    color: ${token.colorText};
    text-align: left;
    cursor: pointer;

    &:hover {
      color: ${token.colorPrimary};
    }
  `,
  mappingRow: css`
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    column-gap: ${token.marginXS}px;
    padding: ${token.paddingXXS}px 0 ${token.paddingXXS}px ${token.paddingLG}px;
  `,
  rowMeta: css`
    grid-column: 1 / -1;
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextSecondary};
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  `,
  was: css`
    text-decoration: line-through;
    color: ${token.colorTextTertiary};
  `,
  note: css`
    padding: ${token.paddingXXS}px 0 0 ${token.paddingLG}px;
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextTertiary};
  `,
  foot: css`
    flex: 0 0 auto;
    padding: ${token.paddingXS}px ${token.padding}px 0 0;
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextTertiary};
  `,
  state: css`
    padding: ${token.paddingLG}px;
    color: ${token.colorTextSecondary};
  `,
  newName: css`
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorText};
  `,
  editor: css`
    flex: 1;
    min-width: 0;
    overflow: auto;
  `
}))
