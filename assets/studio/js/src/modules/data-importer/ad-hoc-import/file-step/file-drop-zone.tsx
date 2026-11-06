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
import { Upload } from 'antd'
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Flex, Icon } from '@pimcore/studio-ui-bundle/components'
import { ACCEPTED_EXTENSIONS } from './file-step-state'

const useStyles = createStyles(({ token, css }) => ({
  zone: css`
    max-width: 560px;
  `,
  icons: css`
    color: ${token.colorIcon};
  `,
  label: css`
    text-align: center;
    color: ${token.colorTextSecondary};
  `
}))

export interface FileDropZoneProps {
  onFile: (file: File) => void
}

/**
 * Built like Studio's `FileDropZone`, which `import-modal` does not export.
 */
export const FileDropZone = ({ onFile }: FileDropZoneProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  return (
    <div className={ styles.zone }>
      <Upload.Dragger
        accept={ ACCEPTED_EXTENSIONS.join(',') }
        beforeUpload={ (file) => {
          onFile(file)

          return false
        } }
        multiple={ false }
        name="file"
        showUploadList={ false }
      >
        <Flex
          align="center"
          gap="mini"
          justify="center"
          style={ { padding: '20px' } }
          vertical
        >
          <div className={ styles.icons }>
            <Flex
              align="center"
              gap="mini"
              justify="center"
            >
              <Icon
                options={ { height: 20, width: 20 } }
                value="new"
              />
              <Icon
                options={ { height: 20, width: 20 } }
                value="drop-target"
              />
            </Flex>
          </div>
          <div className={ styles.label }>{ t('data-importer.ad-hoc-import.file.drop') }</div>
        </Flex>
      </Upload.Dragger>
    </div>
  )
}
