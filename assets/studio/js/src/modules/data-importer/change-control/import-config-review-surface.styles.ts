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

/** ConfigSummary's spine node; with the gap beside it, this is what the rail leaves room for */
const NODE = 7

export const useStyles = createStyles(({ token, css }) => ({
  /* the modal's body already reaches the modal's edges and rules itself off from the title
     above and the actions below; the review only has to fill it */
  layout: css`
    display: flex;
    align-items: stretch;
    flex: 1 1 auto;
    height: 70vh;
    min-height: 420px;
  `,
  /*
   * The summary's spine markers hang to the left of its text, so the entries line up with the
   * configuration's name above them. Rail and scroller each borrow that much of the modal
   * body's gutter and give it straight back as padding: the head stays where it was, the
   * markers have somewhere to hang, and neither box clips them. Matches ConfigSummary's hang.
   */
  rail: css`
    width: 320px;
    flex: 0 0 320px;
    min-height: 0;
    display: flex;
    flex-direction: column;
    margin-inline-start: -${NODE + token.marginXS}px;
    padding-inline-start: ${NODE + token.marginXS}px;
    /* the modal body already provides the left gutter; only the divider needs clearing */
    padding-right: ${token.paddingLG}px;
    border-right: 1px solid ${token.colorSplit};
  `,
  list: css`
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    margin-inline-start: -${NODE + token.marginXS}px;
    padding-inline-start: ${NODE + token.marginXS}px;
  `,
  summary: css`
    padding: ${token.paddingXS}px 0 ${token.paddingXXS}px;
    font-size: ${token.fontSizeSM}px;
    font-weight: ${token.fontWeightStrong};
    letter-spacing: .08em;
    text-transform: uppercase;
    color: ${token.colorTextTertiary};
  `,
  /* the twin of the change-control review header: one quiet line, the notice in a tooltip */
  history: css`
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 0 ${token.paddingLG}px;
    margin-bottom: ${token.marginSM}px;
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextSecondary};
  `,
  historyMeta: css`
    display: inline-flex;
    align-items: center;
    gap: 6px;
  `,
  historyDivider: css`
    color: ${token.colorTextQuaternary};
  `,
  historySnapshot: css`
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: ${token.colorTextTertiary};
    border-bottom: 1px dashed ${token.colorBorder};
    cursor: help;
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
  state: css`
    padding: ${token.paddingLG}px;
    color: ${token.colorTextSecondary};
  `,
  editor: css`
    flex: 1;
    min-width: 0;
    overflow: auto;
    padding: 0 ${token.paddingLG}px;
  `
}))
