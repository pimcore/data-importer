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

export const useStyles = createStyles(({ css, token }) => {
  return {
    twoColumnLayout: css`
      display: flex;
      gap: ${token.paddingXS}px;
      min-height: 240px;
    `,

    leftColumn: css`
      flex: 0 0 calc(50% - 4px);
      min-width: 0;
      background: ${token.colorFillAdditional};
      border-radius: ${token.borderRadius}px;
      display: flex;
      flex-direction: column;
    `,

    leftHeader: css`
      padding: ${token.paddingXXS}px ${token.paddingXS}px;
      height: 32px;
      display: flex;
      align-items: center;
    `,

    leftHeaderTitle: css`
      font-size: 12px;
    `,

    fieldsContainer: css`
      padding: 0 ${token.paddingXS}px ${token.paddingXS}px ${token.paddingXS}px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;

      & > div {
        min-width: 0;
      }
    `,

    fieldLabel: css`
      font-size: 12px;
      margin-bottom: ${token.paddingXXS}px;
    `,

    overwriteLabel: css`
      font-size: 12px;
      margin-top: 2px;
    `,

    switchLabel: css`
      font-size: 12px;
    `,

    selectFull: css`
      width: 100%;
      height: 32px;
    `,

    selectSkeletonWrapper: css`
      width: 100%;
      min-width: 0;

      & > * {
        width: 100%;
        min-width: 0;
      }
    `,

    typeError: css`
      color: ${token.colorErrorText};
      font-size: 12px;
      line-height: 18px;
      background: ${token.colorErrorBg};
      border: 1px solid ${token.colorErrorBorder};
      border-radius: ${token.borderRadius}px;
      padding: ${token.paddingXXS}px ${token.paddingXS}px;
    `,

    classificationStoreKeyRow: css`
      display: flex;
      align-items: center;
      gap: ${token.paddingXS}px;
    `,

    classificationStoreKeyInput: css`
      flex: 1;
    `,

    rightColumn: css`
      flex: 0 0 calc(50% - 4px);
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    `,

    previewWrapper: css`
      flex: 1;
      overflow: hidden;
    `
  }
})
