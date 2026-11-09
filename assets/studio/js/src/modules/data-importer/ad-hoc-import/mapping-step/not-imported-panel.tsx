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
import { Button, Flex, Input, Text } from '@pimcore/studio-ui-bundle/components'
import { type MappingRow } from './mapping-state'

const MAPPING = 'data-importer.ad-hoc-import.mapping'
const SEARCH_WIDTH = 260

const useStyles = createStyles(({ token, css }) => ({
  area: css`
    margin-top: ${token.marginSM}px;
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: ${token.borderRadiusLG}px;
    background: ${token.colorBgLayout};
  `,
  toggle: css`
    all: unset;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    width: 100%;
    padding: ${token.paddingXS}px ${token.paddingSM}px;
    cursor: pointer;
  `,
  title: css`
    font-weight: ${token.fontWeightStrong};
  `,
  names: css`
    flex: 1 1 0;
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
    padding: 0 ${token.paddingSM}px ${token.paddingSM}px;
  `,
  columns: css`
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
  `,
  column: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    padding: ${token.paddingXXS}px ${token.paddingXS}px;
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: ${token.borderRadius}px;
    background: ${token.colorBgContainer};
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
    <div className={ styles.area }>
      <button
        aria-expanded={ isOpen }
        className={ styles.toggle }
        onClick={ () => { setIsOpen(!isOpen) } }
        type="button"
      >
        <span className={ styles.title }>
          { `${isOpen ? '▾' : '▸'} ${t(`${MAPPING}.not-imported`, { count: skipped.length })}` }
        </span>
        <span className={ styles.names }>{ skipped.map(({ label }) => label).join(', ') }</span>
        <span className={ styles.action }>{ t(`${MAPPING}.not-imported.${isOpen ? 'hide' : 'show'}`) }</span>
      </button>
      { isOpen && (
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
              <div
                className={ styles.column }
                key={ column }
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
              </div>
            )) }
          </div>
        </div>
      ) }
    </div>
  )
}
