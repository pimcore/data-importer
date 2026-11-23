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
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Button, Card, Flex, Tag, Text } from '@pimcore/studio-ui-bundle/components'
import { isAllowed } from '@pimcore/studio-ui-bundle/modules/auth'
import { useWidgetManager } from '@pimcore/studio-ui-bundle/modules/widget-manager'
import { type InspectedFile } from '../file-step/file-step-state'
import { type ImportConfiguration } from './configuration-state'

const LABEL_WIDTH = 160
const CONFIGURATION = 'data-importer.ad-hoc-import.configuration'
// the Data Importer's own loaders; one another bundle registers is named by its type
const KNOWN_SOURCES = new Set(['sftp', 'http', 'asset', 'upload', 'push', 'sql'])
// the permission and the widget of Data Hub's own main navigation item
export const DATA_HUB_PERMISSION = 'plugin_datahub_config'
export const DATA_HUB_WIDGET = {
  name: 'Data Hub Configuration',
  id: 'data-hub-configuration',
  component: 'data-hub-configuration',
  config: {
    translationKey: 'data-hub.configuration',
    icon: { type: 'name' as const, value: 'datahub' }
  }
}

const useStyles = createStyles(({ token, css }) => ({
  title: css`
    font-size: ${token.fontSizeLG}px;
  `,
  spacer: css`
    flex-grow: 1;
  `,
  facts: css`
    display: grid;
    grid-template-columns: ${LABEL_WIDTH}px minmax(0, 1fr);
    gap: ${token.marginXS}px ${token.margin}px;
  `,
  // a small mark beside the name, not a tag of the default size
  readOnly: css`
    &.ant-tag {
      margin: 0;
      padding: 0 6px;
      background: ${token.colorFillTertiary};
      font-size: 10px;
      line-height: 18px;
    }
  `,
  replaced: css`
    color: ${token.colorTextQuaternary};
  `,
  matches: css`
    color: ${token.colorSuccess};
    font-weight: ${token.fontWeightStrong};
  `
}))

const sourceOf = (t: ReturnType<typeof useTranslation>['t'], source: string): string =>
  KNOWN_SOURCES.has(source) ? t(`${CONFIGURATION}.source.${source}`) : source

// the file format as the configuration reads it: type, sheet and header row
const formatOf = (t: ReturnType<typeof useTranslation>['t'], configuration: ImportConfiguration): string => [
  configuration.format?.toUpperCase() ?? '',
  configuration.sheet === null ? '' : t(`${CONFIGURATION}.format.sheet`, { sheet: configuration.sheet, interpolation: { escapeValue: false } }),
  configuration.headerRow ? t(`${CONFIGURATION}.format.header-row`) : ''
].filter((part) => part !== '').join(', ')

export interface ConfigurationDetailProps {
  configuration: ImportConfiguration
  inspected?: InspectedFile
  // the uploaded file, which the configuration reads instead of its own source
  fileName?: string
  // what it finds the objects by, as a person reads it
  match: string
}

// what the chosen configuration does with this file, read only: its matching and folder replace the file step's; its card names its class
export const ConfigurationDetail = ({ configuration, inspected, fileName, match }: ConfigurationDetailProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()
  const { openMainWidget } = useWidgetManager()
  const mayOpenDataHub = isAllowed(DATA_HUB_PERMISSION)

  return (
    <section aria-label={ configuration.name }>
      <Flex
        gap="small"
        vertical
      >
        <Flex
          align="center"
          gap="small"
        >
          <Text
            className={ styles.title }
            strong
          >
            { configuration.name }
          </Text>
          <Tag className={ styles.readOnly }>{ t(`${CONFIGURATION}.read-only`) }</Tag>
          <span className={ styles.spacer } />
          { mayOpenDataHub && (
            <Button
              onClick={ () => { openMainWidget(DATA_HUB_WIDGET) } }
              type="link"
            >
              { t(`${CONFIGURATION}.open-in-data-hub`) }
            </Button>
          ) }
        </Flex>
        <Card>
          <div className={ styles.facts }>
            <Text type="secondary">{ t(`${CONFIGURATION}.source`) }</Text>
            <Text>
              { configuration.source !== null && (
                <>
                  <s className={ styles.replaced }>
                    { [sourceOf(t, configuration.source), configuration.sourcePath ?? ''].join(' ').trim() }
                  </s>
                  { ' → ' }
                </>
              ) }
              { t(`${CONFIGURATION}.source.this-upload`, { file: fileName ?? '', interpolation: { escapeValue: false } }) }
            </Text>
            <Text type="secondary">{ t(`${CONFIGURATION}.file-format`) }</Text>
            <Text>
              { formatOf(t, configuration) }
              { configuration.format === inspected?.type && (
                <>
                  { ' ' }
                  <span className={ styles.matches }>{ t(`${CONFIGURATION}.format.matches`) }</span>
                </>
              ) }
            </Text>
            <Text type="secondary">{ t(`${CONFIGURATION}.match-by`) }</Text>
            <Text>{ match }</Text>
            <Text type="secondary">{ t(`${CONFIGURATION}.new-objects-go-to`) }</Text>
            <Text>{ configuration.newObjectsFolder }</Text>
            <Text type="secondary">{ t(`${CONFIGURATION}.mapping`) }</Text>
            <Text>
              { t(`${CONFIGURATION}.${configuration.fieldCount === 1 ? 'mapping-count-one' : 'mapping-count'}`, {
                count: configuration.fieldCount,
                transformed: configuration.transformedCount
              }) }
            </Text>
          </div>
        </Card>
      </Flex>
    </section>
  )
}
