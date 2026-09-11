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

/* the rail's caption and row are buttons stripped back to text: the keyboard reaches them */
const plain = `
  display: flex;
  align-items: center;
  width: 100%;
  border: none;
  background: none;
  margin: 0;
  font: inherit;
  text-align: left;
  cursor: pointer;
`

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
    width: 272px;
    flex: 0 0 272px;
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
    gap: ${token.marginMD}px;
  `,
  summary: css`
    padding: ${token.paddingXS}px 0 ${token.paddingXXS}px;
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
    gap: ${token.marginXXS}px;
  `,
  caption: css`
    ${plain}
    padding: 0 0 ${token.paddingXXS}px;
    font-size: ${token.fontSizeSM}px;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorTextSecondary};

    &:hover {
      color: ${token.colorPrimary};
    }
  `,
  captionActive: css`
    ${plain}
    padding: 0 0 ${token.paddingXXS}px;
    font-size: ${token.fontSizeSM}px;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorPrimary};
  `,
  row: css`
    ${plain}
    gap: ${token.marginXS}px;
    min-height: ${token.controlHeightSM}px;
    padding: 0 ${token.paddingXXS}px 0 ${token.paddingSM}px;
    border-radius: ${token.borderRadiusSM}px;
    color: ${token.colorText};

    &:hover {
      background: ${token.colorFillTertiary};
    }
  `,
  rowTarget: css`
    ${plain}
    gap: ${token.marginXS}px;
    min-height: ${token.controlHeightSM}px;
    padding: 0 ${token.paddingXXS}px 0 ${token.paddingSM}px;
    border-radius: ${token.borderRadiusSM}px;
    color: ${token.colorText};
    background: ${token.colorPrimaryBg};
  `,
  rowLabel: css`
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  `,
  note: css`
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
