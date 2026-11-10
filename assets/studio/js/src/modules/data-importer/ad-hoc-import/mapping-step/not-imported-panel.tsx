/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useState } from 'react'
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Button, Card, Collapse, Flex, Input, Text } from '@pimcore/studio-ui-bundle/components'
import { type MappingRow } from './mapping-state'

const MAPPING = 'data-importer.ad-hoc-import.mapping'
const SEARCH_WIDTH = 260
const AREA = 'not-imported'

const useStyles = createStyles(({ token, css }) => ({
  area: css`
    margin-top: ${token.marginSM}px;
  `,
  names: css`
    display: block;
    min-width: 0;
    overflow: hidden;
    color: ${token.colorTextTertiary};
    white-space: nowrap;
    text-overflow: ellipsis;
  `,
  action: css`
    color: ${token.colorPrimary};
  `,
  body: css`
    display: flex;
    flex-direction: column;
    gap: ${token.marginXS}px;
  `,
  columns: css`
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
  `,
  columnName: css`
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    min-width: 0;
  `,
  sample: css`
    overflow: hidden;
    font-size: ${token.fontSizeSM}px;
    line-height: 16px;
    color: ${token.colorTextTertiary};
    white-space: nowrap;
    text-overflow: ellipsis;
  `
}))

export interface NotImportedPanelProps {
  skipped: MappingRow[]
  onRestore: (column: string) => void
}

export const NotImportedPanel = ({ skipped, onRestore }: NotImportedPanelProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const [isOpen, setIsOpen] = useState(false)
  const [search, setSearch] = useState('')
  const term = search.trim().toLowerCase()
  const columns = term === '' ? skipped : skipped.filter(({ label }) => label.toLowerCase().includes(term))

  return (
    <Collapse
      activeKeys={ isOpen ? [AREA] : [] }
      className={ styles.area }
      expandIconPosition="start"
      items={ [{
        key: AREA,
        label: t(`${MAPPING}.not-imported`, { count: skipped.length }),
        subLabel: <span className={ styles.names }>{ skipped.map(({ label }) => label).join(', ') }</span>,
        extra: <span className={ styles.action }>{ t(`${MAPPING}.not-imported.${isOpen ? 'hide' : 'show'}`) }</span>,
        children: (
          <div className={ styles.body }>
            <Flex
              align="center"
              gap="small"
            >
              <Input
                aria-label={ t(`${MAPPING}.not-imported.search`) }
                onChange={ (event: React.ChangeEvent<HTMLInputElement>) => { setSearch(event.target.value) } }
                placeholder={ t(`${MAPPING}.not-imported.search`) }
                style={ { width: SEARCH_WIDTH } }
                value={ search }
              />
              <Text type="secondary">{ t(`${MAPPING}.not-imported.note`) }</Text>
            </Flex>
            <div className={ styles.columns }>
              { columns.map(({ column, label, sample }) => (
                <Card
                  contentPadding={ { x: 'mini', y: 'mini' } }
                  key={ column }
                >
                  <Flex
                    align="center"
                    gap="small"
                  >
                    <span className={ styles.columnName }>
                      <Text strong>{ label }</Text>
                      { sample !== undefined && (
                        <span className={ styles.sample }>
                          { t(`${MAPPING}.sample`, { value: sample, interpolation: { escapeValue: false } }) }
                        </span>
                      ) }
                    </span>
                    <Button
                      aria-label={ t(`${MAPPING}.restore-column`, { column: label, interpolation: { escapeValue: false } }) }
                      onClick={ () => { onRestore(column) } }
                      size="small"
                    >
                      { t(`${MAPPING}.restore`) }
                    </Button>
                  </Flex>
                </Card>
              )) }
            </div>
          </div>
        )
      }] }
      onChange={ (keys) => { setIsOpen(keys.includes(AREA)) } }
      size="small"
    />
  )
}
