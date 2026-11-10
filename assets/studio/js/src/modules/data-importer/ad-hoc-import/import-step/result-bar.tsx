/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { type ImportRun } from './import-state'
import { useStyles } from './import-step.styles'

// the top of the result card: each outcome by its share
export const ResultBar = ({ run }: { run: ImportRun }): React.JSX.Element => {
  const { styles } = useStyles()

  return (
    <div
      aria-hidden
      className={ styles.resultBar }
      data-testid="result-bar"
    >
      <span
        className={ styles.resultBarUpdated }
        style={ { flexGrow: run.updated } }
      />
      <span
        className={ styles.resultBarCreated }
        style={ { flexGrow: run.created } }
      />
      <span
        className={ styles.resultBarSkipped }
        style={ { flexGrow: run.skipped } }
      />
    </div>
  )
}
