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
import { Form, FormKit, Space } from '@pimcore/studio-ui-bundle/components'
import { type BundleDataImporterAdHocImportFileSettings } from '../../data-importer-api-slice.gen'
import { FileCard } from './file-card'
import { FileDropZone } from './file-drop-zone'
import { FileSettings } from './file-settings'
import { FirstRows } from './first-rows'
import { ObjectsPanel } from './objects-panel'
import { RefusalAlert } from './refusal-alert'
import { type FilePhase, type ObjectsSettings } from './file-step-state'
import { PANEL_PADDING } from '../dialog-layout'

// the panel title's height left after Studio's 38px, so the empty step fits a 720px high window as designed
const PANEL_TITLE_HEIGHT = 28

// Studio's panel insets its title and the form its labels; the step keeps one left edge, as the header has
const useStyles = createStyles(({ css }) => ({
  step: css`
    .ant-card .ant-card-head {
      padding-inline: 0;
      min-height: ${PANEL_TITLE_HEIGHT}px;
    }

    &.ant-form-vertical .ant-form-item-row > .ant-form-item-label {
      padding-inline: 0;
    }
  `
}))

export interface FileStepProps {
  phase: FilePhase
  isInspecting: boolean
  objects: ObjectsSettings
  onFile: (file: File) => void
  onRemoveFile: () => void
  onSettingsChange: (changed: Partial<BundleDataImporterAdHocImportFileSettings>) => void
  onObjectsChange: (objects: ObjectsSettings) => void
}

export const FileStep = ({ phase, isInspecting, objects, onFile, onRemoveFile, onSettingsChange, onObjectsChange }: FileStepProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  const inspected = phase.kind === 'inspected' ? phase.inspected : undefined
  const refused = inspected?.refusal !== undefined && inspected.refusal !== null
  // a file not kept has no settings left to change
  const fileId = inspected?.fileId ?? null

  return (
    <Form
      className={ styles.step }
      layout="vertical"
    >
      <Space
        className="w-full"
        direction="vertical"
        size="normal"
      >
        <FormKit.Panel
          contentPadding={ PANEL_PADDING }
          title={ t('data-importer.ad-hoc-import.file.title') }
        >
          <Space
            className="w-full"
            direction="vertical"
            size="small"
          >
            { phase.kind === 'empty'
              ? <FileDropZone onFile={ onFile } />
              : (
                <FileCard
                  onRemove={ onRemoveFile }
                  phase={ phase }
                />
                ) }
            { inspected !== undefined && <RefusalAlert inspected={ inspected } /> }
            { inspected !== undefined && fileId !== null && (
              <FileSettings
                inspected={ inspected }
                // the settings restart from what the server read when another file comes
                key={ fileId }
                onChange={ onSettingsChange }
              />
            ) }
            { inspected !== undefined && fileId !== null && !refused && (
              <FirstRows
                inspected={ inspected }
                isLoading={ isInspecting }
              />
            ) }
          </Space>
        </FormKit.Panel>

        { (phase.kind === 'empty' || (inspected !== undefined && !refused)) && (
          <ObjectsPanel
            inspected={ inspected }
            objects={ objects }
            onChange={ onObjectsChange }
          />
        ) }
      </Space>
    </Form>
  )
}
