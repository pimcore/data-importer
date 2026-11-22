/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { createStyles } from '@pimcore/studio-ui-bundle/app'

// the grid of a row and of the table's head
export const ROW_COLUMNS = '16px 240px 24px minmax(0, 1fr) 150px 200px 110px'

export const useStyles = createStyles(({ token, css }) => ({
  row: css`
    display: grid;
    grid-template-columns: ${ROW_COLUMNS};
    align-items: center;
    min-height: 40px;
    border-bottom: 1px solid ${token.colorBorderSecondary};
  `,
  active: css`
    background: ${token.controlItemBgActive};
    box-shadow: inset 3px 0 0 ${token.colorPrimary};
  `,
  dotCell: css`
    display: flex;
    justify-content: center;
  `,
  dot: css`
    width: 8px;
    height: 8px;
    border-radius: 50%;
  `,
  dotMapped: css`
    background: ${token.colorSuccess};
  `,
  dotOpen: css`
    background: ${token.colorWarning};
  `,
  cell: css`
    padding: ${token.paddingXXS}px;
    min-width: 0;
  `,
  sample: css`
    font-size: ${token.fontSizeSM}px;
    line-height: 16px;
    color: ${token.colorTextTertiary};
  `,
  arrow: css`
    color: ${token.colorTextDescription};
  `,
  fieldButton: css`
    all: unset;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    flex: 0 1 auto;
    min-width: 0;
    height: 30px;
    padding: 0 ${token.paddingXS}px 0 ${token.paddingXXS}px;
    border-radius: ${token.borderRadius}px;
    cursor: pointer;

    &:hover {
      background: ${token.colorFillAdditional};
    }

    &:focus-visible {
      outline: 2px solid ${token.colorPrimaryBorder};
    }

    &:hover .field-pen, &:focus-visible .field-pen {
      opacity: 1;
    }
  `,
  pen: css`
    flex: none;
    color: ${token.colorPrimary};
    opacity: 0;
  `,
  fieldIcon: css`
    display: flex;
    flex: none;
    align-items: center;
    color: ${token.colorPrimary};
  `,
  // the field's name stays whole; the group path gives way
  fieldName: css`
    flex: none;
    font-weight: ${token.fontWeightStrong};
    white-space: nowrap;
  `,
  // the small chip of the design, not the default tag's size
  auto: css`
    &.ant-tag {
      display: inline-flex;
      flex: none;
      align-items: center;
      gap: 3px;
      margin: 0;
      padding: 0 6px;
      border-radius: 8px;
      font-size: 10px;
      line-height: 16px;
    }

    & > span + span {
      margin-inline-start: 0;
    }
  `,
  needsTransformation: css`
    &.ant-tag {
      color: ${token.Colors.Brand.Warning.colorWarningText};
    }
  `,
  // the group gives way before the field's name, down to nothing
  crumb: css`
    flex: 1 1 0;
    min-width: 0;
    overflow: hidden;
    color: ${token.colorTextDescription};
    white-space: nowrap;
    text-overflow: ellipsis;
  `,
  suggestion: css`
    all: unset;
    display: flex;
    align-items: center;
    gap: ${token.marginXXS}px;
    flex: none;
    height: 24px;
    padding: 0 ${token.paddingXS}px;
    border-radius: 12px;
    white-space: nowrap;
    background: ${token.colorPrimaryBg};
    color: ${token.colorPrimaryActive};
    cursor: pointer;

    &:focus-visible {
      outline: 2px solid ${token.colorPrimaryBorder};
    }
  `,
  suggestionIcon: css`
    display: flex;
    flex: none;
    color: ${token.colorPrimary};
  `,
  suggestionLead: css`
    color: ${token.colorTextTertiary};
  `,
  skip: css`
    display: flex;
    justify-content: flex-end;
  `
}))
