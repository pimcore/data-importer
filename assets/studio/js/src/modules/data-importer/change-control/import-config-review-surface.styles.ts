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
  /* the headline is the section's name at body size, and the whole line opens the section:
     a button stripped back to text, with the arrow as its one ornament */
  headline: css`
    display: inline-flex;
    align-items: center;
    gap: ${token.marginXS}px;
    min-height: ${token.controlHeight}px;
    border: none;
    background: none;
    padding: 0;
    margin: 0;
    font: inherit;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorText};
    cursor: pointer;

    &:hover {
      color: ${token.colorPrimary};
    }
  `,
  headlineActive: css`
    display: inline-flex;
    align-items: center;
    gap: ${token.marginXS}px;
    min-height: ${token.controlHeight}px;
    border: none;
    background: none;
    padding: 0;
    margin: 0;
    font: inherit;
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorPrimary};
    cursor: pointer;
  `,
  /* a listing: the field's name from the left edge, its mark at the end, one hairline apart */
  items: css`
    list-style: none;
    margin: 0;
    padding: 0;
  `,
  item: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    min-height: ${token.controlHeightSM}px;
    padding: ${token.paddingXXS}px 0;
    border-bottom: 1px solid ${token.colorSplit};
    color: ${token.colorText};

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
  /* a new configuration in one glance: source above target on a connector, a card apart
     from the editor, and how it runs as tags beneath */
  brief: css`
    display: flex;
    flex-direction: column;
    gap: ${token.marginSM}px;
    margin-right: ${token.margin}px;
  `,
  briefFlow: css`
    display: flex;
    flex-direction: column;
    padding: ${token.paddingSM}px;
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: ${token.borderRadiusLG}px;
    background: ${token.colorFillQuaternary};
  `,
  briefStop: css`
    display: flex;
    align-items: center;
    gap: ${token.marginSM}px;
    min-width: 0;
  `,
  briefIcon: css`
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: ${token.colorBgContainer};
    border: 1px solid ${token.colorBorderSecondary};
    color: ${token.colorPrimary};
  `,
  briefText: css`
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: ${token.lineHeightSM};
  `,
  briefLabel: css`
    font-weight: ${token.fontWeightStrong};
    color: ${token.colorText};
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  `,
  briefNote: css`
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextSecondary};
  `,
  /* the line from source to target, hung under the icon's centre */
  briefConnector: css`
    width: 1px;
    height: ${token.marginSM}px;
    margin: ${token.marginXXS}px 0 ${token.marginXXS}px 14px;
    background: ${token.colorBorder};
  `,
  briefTags: css`
    display: flex;
    flex-wrap: wrap;
    gap: ${token.marginXXS}px;
  `,
  note: css`
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextTertiary};
  `,
  state: css`
    padding: ${token.paddingLG}px;
    color: ${token.colorTextSecondary};
  `,
  editor: css`
    flex: 1;
    min-width: 0;
    overflow: auto;
  `
}))
