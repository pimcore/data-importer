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
import {
  ClassificationStoreKeyModal,
  type ClassificationStoreKeyRow,
  type ClassificationStoreKeysQuery
} from '../../components/tabs/steps/advanced-mapping-modal/step-target/classification-store-key-modal/classification-store-key-modal'
import { useBundleDataImporterAdHocImportLoadClassificationStoreKeysQuery } from '../../data-importer-api-slice-enhanced'
import { type StoreKey } from './mapping-state'

// the ad hoc import's own endpoint, under its right; every key, whatever its type
export const useAdHocStoreKeys: ClassificationStoreKeysQuery = ({ classId, fieldName, start, limit, searchfilter }, options) =>
  useBundleDataImporterAdHocImportLoadClassificationStoreKeysQuery({ classId, fieldName, start, limit, searchfilter }, options)

export const storeKeyOf = (keyId: string, row?: ClassificationStoreKeyRow): StoreKey => ({
  id: keyId,
  label: row?.groupName !== undefined && row.keyName !== undefined ? `${row.groupName} › ${row.keyName}` : keyId
})

export interface StoreKeyDialogProps {
  classId: string
  fieldName: string
  onClose: () => void
  onSelect: (key: StoreKey) => void
}

export const StoreKeyDialog = ({ classId, fieldName, onClose, onSelect }: StoreKeyDialogProps): React.JSX.Element => (
  <ClassificationStoreKeyModal
    classId={ classId }
    fieldName={ fieldName }
    onClose={ onClose }
    onSelect={ (keyId, row) => { onSelect(storeKeyOf(keyId, row)) } }
    open
    transformationResultType=""
    useKeysQuery={ useAdHocStoreKeys }
  />
)
