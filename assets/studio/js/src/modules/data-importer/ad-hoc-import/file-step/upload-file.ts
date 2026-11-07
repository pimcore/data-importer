/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { getPrefix } from '@pimcore/studio-ui-bundle/api'
import { type BundleDataImporterAdHocImportFileResponse } from '../../data-importer-api-slice.gen'

export class UploadAborted extends Error {}

export class UploadFailed extends Error {
  constructor (readonly data: unknown) {
    super('Upload failed')
  }
}

export interface RunningUpload {
  result: Promise<BundleDataImporterAdHocImportFileResponse>
  abort: () => void
}

const parse = (text: string): unknown => {
  try {
    return JSON.parse(text)
  } catch {
    return text
  }
}

/**
 * Through XMLHttpRequest, not the API slice: only it reports how far the upload is and can be aborted midway.
 */
export const uploadFile = (
  file: File,
  folderId: number,
  onProgress: (loaded: number, total: number) => void
): RunningUpload => {
  const request = new XMLHttpRequest()

  const result = new Promise<BundleDataImporterAdHocImportFileResponse>((resolve, reject) => {
    request.upload.onprogress = (event) => { onProgress(event.loaded, event.total) }
    request.onabort = () => { reject(new UploadAborted()) }
    request.onerror = () => { reject(new UploadFailed(null)) }
    request.onload = () => {
      const data = parse(request.responseText)
      if (request.status >= 200 && request.status < 300) {
        resolve(data as BundleDataImporterAdHocImportFileResponse)
      } else {
        reject(new UploadFailed(data))
      }
    }

    const body = new FormData()
    body.append('file', file)
    body.append('folderId', String(folderId))

    request.open('POST', `${getPrefix()}/bundle/data-importer/ad-hoc-import/file`)
    request.send(body)
  })

  return { result, abort: () => { request.abort() } }
}
