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
  `,
  /* the headline is the section's name at body size; the arrow beside it is the only control */
  headline: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXXS}px;
    min-height: ${token.controlHeight}px;
  `,
  headlineLabel: css`
    font-size: ${token.fontSize}px;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorText};
  `,
  headlineActive: css`
    font-size: ${token.fontSize}px;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorPrimary};
  `,
  /* a listing, not controls: the field's name and its mark, one hairline apart */
  item: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    min-height: ${token.controlHeightSM}px;
    padding: ${token.paddingXXS}px 0 ${token.paddingXXS}px ${token.paddingSM}px;
    border-bottom: 1px solid ${token.colorSplit};
    color: ${token.colorTextSecondary};

    &:last-child {
      border-bottom: none;
    }
  `,
  itemLabel: css`
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
