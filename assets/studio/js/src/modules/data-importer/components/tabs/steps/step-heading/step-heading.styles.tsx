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
    stepHeading: css`
      color: ${token.colorPrimary};
      font-size: 14px;
      font-weight: ${token.fontWeightStrong};
      height: 32px;
      line-height: 32px;
      margin: 0;
      padding-left: ${token.paddingXXS}px;
    `
  }
})
