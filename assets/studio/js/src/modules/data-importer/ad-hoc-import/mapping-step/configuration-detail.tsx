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
import { Card, Flex, Tag, Text } from '@pimcore/studio-ui-bundle/components'
import { type InspectedFile } from '../file-step/file-step-state'
import { type ImportConfiguration } from './configuration-state'

const LABEL_WIDTH = 160
const CONFIGURATION = 'data-importer.ad-hoc-import.configuration'
// the Data Importer's own loaders; one another bundle registers is named by its type
const KNOWN_SOURCES = ['sftp', 'http', 'asset', 'upload', 'push', 'sql']

const useStyles = createStyles(({ token, css }) => ({
  title: css`
    font-size: ${token.fontSizeLG}px;
  `,
  facts: css`
    display: grid;
    grid-template-columns: ${LABEL_WIDTH}px minmax(0, 1fr);
    gap: ${token.marginXS}px ${token.margin}px;
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
  KNOWN_SOURCES.includes(source) ? t(`${CONFIGURATION}.source.${source}`) : source

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

// what the chosen configuration does with this file, read only: its class, matching and folder replace the file step's
export const ConfigurationDetail = ({ configuration, inspected, fileName, match }: ConfigurationDetailProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

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
          <Tag>{ t(`${CONFIGURATION}.read-only`) }</Tag>
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
            <Text type="secondary">{ t('data-importer.ad-hoc-import.objects.class') }</Text>
            <Text>{ configuration.className ?? configuration.classId }</Text>
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
