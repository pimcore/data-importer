/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

/* eslint-disable max-lines */

import { createStyles } from 'antd-style'

export const useStyles = createStyles(({ css, token }) => {
  return {
    // ── Layout ────────────────────────────────────────────────────────────

    // Three-column flex layout: left(flex:2) + center(46px fixed) + right(flex:3)
    // DataSetupTab gives this div a real bounded height via flex:1 + min-height:0,
    // so height:100% and overflow:hidden here are clean — no JS measurement needed.
    // min-width:800px combined with overflow:auto on stepContentMapping shows a
    // horizontal scrollbar if the viewport is too narrow.
    mappingLayout: css`
      width: 100%;
      height: 100%;
      min-width: 900px;
    `,

    mappingLayoutLeft: css`
      flex: 2;
      min-width: 0;
      height: 100%;
      overflow-y: scroll;
    `,

    // Center column: full height, no overflow — contains the sticky arrow.
    mappingLayoutCenter: css`
      width: 46px;
      flex-shrink: 0;
      height: 100%;
    `,

    // Column is exactly the available height, so top:50% is the true midpoint.
    mappingLayoutCenterArrow: css`
      position: sticky;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      color: ${token.colorFillActive};
    `,

    mappingLayoutRight: css`
      flex: 3;
      min-width: 0;
      height: 100%;
      overflow-y: scroll;
    `,

    panel: css`
      height: 100%;
    `,

    panelScrollable: css`
      flex: 1;
      min-height: 0;
      padding: 0 ${token.paddingSM}px ${token.paddingMD}px;
    `,

    // ── Sources panel ─────────────────────────────────────────────────────

    sourcesPanel: css`
      height: 100%;
      background: ${token.colorFillTertiary};
    `,

    sourcesHeader: css`
      padding: 0 ${token.paddingMD}px;
      height: 52px;
      box-sizing: border-box;
      flex-shrink: 0;
    `,

    sourcesTitle: css`
      color: ${token.colorPrimary};
      font-weight: 600;
      font-size: ${token.fontSizeLG}px;
      margin: 0;
    `,

    // ── Source row cards (Figma design) ───────────────────────────────────

    // Outer wrapper: left-border accent + card shadow + radius
    sourceRowOuter: css`
      border-left: 2px solid ${token.colorBorder};
      border-radius: ${token.borderRadiusLG}px;
      box-shadow: ${token.boxShadowTertiary};
      transition: opacity 0.15s;
      position: relative;

      &:hover .source-add-btn {
        opacity: 1;
        pointer-events: auto;
      }
    `,

    // Mapped rows get the purple left-border accent
    sourceRowOuterMapped: css`
      border-left-color: ${token.colorPrimaryBorderHover};
    `,

    // Faded rows (filter active, not matching)
    sourceRowOuterFaded: css`
      opacity: 0.4;
    `,

    // Inner card: white bg, border on 3 sides, full radius, flex row
    sourceRowInner: css`
      background: ${token.colorBgContainer};
      border: 1px solid ${token.colorBorderSecondary};
      border-left: none;
      border-radius: ${token.borderRadiusLG}px;
      min-height: 40px;
      overflow: hidden;
    `,

    // Mapped rows: pointer cursor — entire row is clickable to toggle filter
    sourceRowInnerMapped: css`
      cursor: pointer;
    `,

    // Unmapped rows: pointer cursor — entire row is clickable to add a mapping
    sourceRowInnerUnmapped: css`
      cursor: pointer;
    `,

    // Left half: tag area — flex: 1, padding, row with gap
    sourceRowTagArea: css`
      flex: 1;
      padding: ${token.paddingXS}px ${token.paddingXS}px;
      min-width: 0;
    `,

    // Inner wrapper for Tag + Badge inside Draggable (keeps them side by side)
    sourceTagInner: css`
      display: inline-flex;
      align-items: center;
    `,

    // "+" IconButton wrapper — hidden by default, revealed on row hover (unmapped rows only)
    sourceAddBtn: css`
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.15s;
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
    `,

    // Right half: preview value — flex: 1, truncated, muted colour
    sourceValue: css`
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      color: ${token.colorTextDescription};
      font-size: ${token.fontSize}px;
      padding: 0 ${token.paddingXS}px;
      min-width: 0;
    `,

    // Wrapper for NoContent in sources panel
    noContentWrapper: css`
      flex: 1;
      padding: ${token.paddingXL}px ${token.paddingMD}px;
    `,

    // Full-width Space wrapper for source rows
    sourceRowsSpace: css`
      width: 100%;
    `,

    // Badge count text color for mapped-count badge
    badgeMappedCount: css`
      & .ant-badge-count {
        background-color: ${token.colorFillActive};
        color: ${token.colorPrimary};
        box-shadow: none;
      }
    `,

    // Droppable wrapper around the entire mapping Panel.
    // Resets ALL SDK DnD visual injections (both default-variant background/border
    // and outline-variant outline) on children — our panelDndWrapper border-color
    // approach handles all DnD visuals directly.
    droppablePanel: css`
      width: 100%;

      & .dnd--drag-active,
      & .dnd--drag-valid,
      & .dnd--drag-error {
        background: unset !important;
        border: unset !important;
        outline: none !important;
      }
    `,

    // Panel-level DnD wrapper — switches the .ant-collapse border to dashed
    // and changes its color during drag states. No outline injected here.
    panelDndWrapper: css`
      & .ant-collapse {
        transition: border-color 0.15s, border-style 0.15s;
      }

      &.dnd--drag-active .ant-collapse {
        border-style: dashed !important;
        border-color: ${token.colorBorder} !important;
      }

      &.dnd--drag-valid .ant-collapse {
        border-style: dashed !important;
        border-color: ${token.colorPrimary} !important;
      }

      &.dnd--drag-error .ant-collapse {
        border-style: dashed !important;
        border-color: ${token.colorError} !important;
      }
    `,

    // Expanded mapping — the open section is highlighted so it reads as the one being
    // edited among the collapsed ones. Needs !important: the SDK draws the panel border
    // from `.ant-collapse.collapse-item--theme-default.collapse-item--bordered`, which
    // outranks this selector, and it is a `border` shorthand. The DnD rules above are
    // !important for the same reason and are one class more specific, so a drag still
    // takes over the border while it is in progress.
    panelExpanded: css`
      & .ant-collapse {
        border-color: ${token.colorPrimaryBorder} !important;
      }
    `,

    // Source drop zone — structural only, no DnD visuals (panel wrapper handles those).
    sourceDropZone: css`
      border-radius: ${token.borderRadius}px;
    `,

    // ── Mappings panel ────────────────────────────────────────────────────
    mappingsHeader: css`
      padding: 0 ${token.paddingMD}px 0 0;
      height: 52px;
      box-sizing: border-box;
      flex-shrink: 0;
    `,

    mappingsTitle: css`
      color: ${token.colorPrimary};
      font-weight: 600;
      font-size: ${token.fontSizeLG}px;
      margin: 0;
      margin-right: ${token.paddingXS}px;
    `,

    mappingsActions: css`
      flex: 1;
    `,

    mappingsDivider: css`
      margin: 0;
      height: 16px;
    `,

    mappingsContent: css`
      flex: 1;
      padding: 0 ${token.paddingMD}px ${token.paddingMD}px 0;
      margin-top: -10px;
    `,

    // ── Empty state ───────────────────────────────────────────────────────
    emptyState: css`
      flex: 1;
      display: flex;
      flex-direction: column;
      height: 100%;
      min-height: 0;

      /* Droppable renders a wrapper div — make it fill too */
      & > div {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
      }
    `,

    emptyStateInner: css`
      flex: 1;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: ${token.colorTextSecondary};
      line-height: 1.6;
      border-radius: ${token.borderRadiusLG}px;
      transition: background 0.15s, outline-color 0.15s;

      &.dnd--drag-active {
        outline: 2px dashed ${token.colorBorderSecondary};
        outline-offset: -2px;
      }

      &.dnd--drag-valid {
        background: ${token.colorFillSecondary};
        outline: 2px dashed ${token.colorInfoBorderHover};
        outline-offset: -2px;
      }

      &.dnd--drag-error {
        outline: 2px dashed ${token.colorErrorBorder};
        outline-offset: -2px;
      }
    `,

    // ── Mapping item ──────────────────────────────────────────────────────
    // Figma: card body px-8px py-12px, then inner content wrapper px-4px
    mappingItemContent: css`
      padding: ${token.paddingSM}px ${token.paddingXS}px;
    `,

    // Figma: label row — gap-8px between input and buttons, px-4px horizontal padding
    mappingLabelRow: css`
      display: flex;
      align-items: center;
      gap: ${token.sizeXS}px;
      padding: 0 ${token.paddingXXS}px;
    `,

    mappingLabelInput: css`
      flex: 1;
    `,

    // Figma: horizontal rule between label row and source/dest section
    // margin-top: 10px (gap between label row and divider), no margin-bottom
    // — the paddingTop on sourcesDestRow provides the 12px gap below the rule
    mappingDivider: css`
      border-top: 1px solid ${token.colorBorderSecondary};
      margin-top: ${token.paddingXS}px;
    `,

    // Figma: source/dest section — pt-12px after the divider border.
    // items-stretch so the arrow column fills the full height of the row,
    // allowing it to vertically center its content (advanced state).
    sourcesDestRow: css`
      display: flex;
      align-items: stretch;
      padding-top: ${token.paddingSM}px;
    `,

    // Figma: source/dest columns — gap-4px between label and select(s)
    sourcesDestCol: css`
      display: flex;
      flex-direction: column;
      gap: ${token.sizeXXS}px;
      flex: 1;
      min-width: 0;
      padding: 0 ${token.paddingXXS}px;
    `,

    // Arrow column: px-12px.
    // Simple/warning/in-progress states: align the arrow SVG with the vertical
    // center of the first Select using flexbox instead of a fixed padding-top.
    // Advanced state: justify-center + gap-10px so gear sits above arrow, both centered.
    arrowCol: css`
      flex-shrink: 0;
      padding: 0 ${token.paddingSM}px;
    `,

    arrowColSimple: css`
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
    `,

    // Spacer that offsets the arrow down by the height of the label line + gap,
    // so the arrow aligns with the vertical centre of the Select control.
    arrowLabelSpacer: css`
      height: calc(22px + ${token.paddingXXS}px);
      flex-shrink: 0;
    `,

    // Flex row that centres the arrow SVG within the select-row height.
    arrowSelectRow: css`
      display: flex;
      align-items: center;
      height: ${token.controlHeight}px;
    `,

    arrowColAdvanced: css`
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 10px;
    `,

    // Warning indicator: small orange exclamation circle shown above the arrow
    arrowWarningBadge: css`
      display: flex;
      align-items: center;
      justify-content: center;
      color: ${token.colorWarning};
      font-size: ${token.fontSizeLG}px;
      line-height: 1;
    `,

    // Transformation icon shown above the arrow when an advanced setup exists
    arrowAdvancedIcon: css`
      display: flex;
      align-items: center;
      justify-content: center;
      color: ${token.colorIcon};
      font-size: ${token.fontSizeLG}px;
      line-height: 1;
    `,

    // Arrow SVG wrapper
    arrowSvg: css`
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    `,

    // Advanced destination: plain text lines (not a Select). Matches token.fontSize so it
    // lines up with the Select it replaces — fontSizeSM rendered it 10px against the 12px
    // used by every control around it.
    destinationTextBlock: css`
      display: flex;
      flex-direction: column;
      justify-content: center;
      /* Fills the destination column and centers within it, so the text stays on the same
         vertical center as the source control and the arrow — including when a multi-source
         Select wraps onto several lines and sourcesDestRow stretches both columns taller.
         The min-height keeps the unwrapped case matching the Select it replaces. */
      flex: 1;
      min-height: ${token.controlHeight}px;
      padding: 0 ${token.paddingXXS}px;
      font-size: ${token.fontSize}px;
      line-height: 22px;
      color: ${token.colorText};
    `,

    // In-progress hint: "Requires advanced setup" text — entire string in warning text color.
    // Occupies the same destination slot, so it follows the same size.
    requiresAdvancedHint: css`
      display: flex;
      align-items: center;
      flex: 1;
      min-height: ${token.controlHeight}px;
      padding: 0 ${token.paddingXXS}px;
      font-size: ${token.fontSize}px;
      line-height: 22px;
      color: ${token.colorWarningText};
    `,

    languageSelect: css`
      flex: 1;
    `,

    // Shown in MappingsPanel when a filter is active but no mapping items reference it.
    // Stacks the message text above a centered "Add" button.
    filterEmptyState: css`
      flex: 1;
      display: flex;
      flex-direction: column;
      width: 100%;
      height: 100%;
      min-height: 0;

      /* Droppable renders a wrapper div — make it fill too */
      & > div {
        flex: 1;
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
      }
    `,

    filterEmptyStateInner: css`
      flex: 1;
      width: 100%;
      height: 100%;
      min-height: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: ${token.paddingSM}px;
      text-align: center;
      color: ${token.colorTextSecondary};
      border-radius: ${token.borderRadiusLG}px;
      transition: background 0.15s, outline-color 0.15s;

      &.dnd--drag-active {
        outline: 2px dashed ${token.colorBorderSecondary};
        outline-offset: -2px;
      }

      &.dnd--drag-valid {
        background: ${token.colorFillSecondary};
        outline: 2px dashed ${token.colorInfoBorderHover};
        outline-offset: -2px;
      }

      &.dnd--drag-error {
        outline: 2px dashed ${token.colorErrorBorder};
        outline-offset: -2px;
      }
    `,

    // Hides a mapping item when its source index doesn't match the active filter
    hiddenItem: css`
      display: none;
    `,

    // ── Mapping drop zone (between items) ─────────────────────────────────
    // Droppable wrapper for the thin insert-bar between mapping items.
    // Kills only the SDK's border/outline injections — leaves background alone
    // so the bar's own .dnd--drag-* background rules still work.
    mappingDropZoneWrapper: css`
      width: 100%;
      padding: 2px 0;

      & .dnd--drag-active,
      & .dnd--drag-valid,
      & .dnd--drag-error {
        border: none !important;
        outline: none !important;
      }
    `,

    // Thin horizontal bar — invisible by default, colored during drag states.
    mappingDropZone: css`
      width: 100%;
      height: 6px;
      border-radius: ${token.borderRadiusSM}px;
      background: transparent;
      transition: background 0.15s;
      pointer-events: none;
      flex-shrink: 0;

      &.dnd--drag-active {
        pointer-events: auto;
        background: ${token.colorBorderSecondary};
      }

      &.dnd--drag-valid {
        pointer-events: auto;
        background: ${token.colorInfoBorderHover};
      }

      &.dnd--drag-error {
        pointer-events: auto;
        background: ${token.colorErrorBorder};
      }
    `,

    // ── New-item entrance animation ───────────────────────────────────────
    // Applied to the wrapper of a newly inserted mapping item for 400ms.
    // Matches the slideInFade pattern used by rule-condition in studio-ui-bundle.
    mappingItemNew: css`
      @keyframes mapping-item-slide-in {
        0% {
          opacity: 0;
          transform: translateY(-12px);
        }
        100% {
          opacity: 1;
          transform: translateY(0);
        }
      }

      animation: mapping-item-slide-in 400ms ease-in-out;
    `
  }
})
