/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { UploadAborted, UploadFailed, uploadFile } from './upload-file'

jest.mock('@pimcore/studio-ui-bundle/api', () => ({ getPrefix: () => '/pimcore-studio/api' }))

class FormDataStub {
  readonly entries: Array<[string, unknown]> = []

  append (key: string, value: unknown): void {
    this.entries.push([key, value])
  }
}

class XhrStub {
  static last: XhrStub
  upload: { onprogress?: (event: { loaded: number, total: number }) => void } = {}
  onabort?: () => void
  onerror?: () => void
  onload?: () => void
  status = 0
  responseText = ''
  method?: string
  url?: string
  body?: FormDataStub

  constructor () {
    XhrStub.last = this
  }

  open (method: string, url: string): void {
    this.method = method
    this.url = url
  }

  send (body: FormDataStub): void {
    this.body = body
  }

  abort (): void {
    this.onabort?.()
  }

  respond (status: number, text: string): void {
    this.status = status
    this.responseText = text
    this.onload?.()
  }
}

const file = { name: 'prices.csv' } as unknown as File

beforeEach(() => {
  Object.assign(globalThis, { XMLHttpRequest: XhrStub, FormData: FormDataStub })
})

describe('uploadFile', () => {
  it('posts the file and its folder and resolves with what the server read', async () => {
    const { result } = uploadFile(file, 77, () => undefined)
    const xhr = XhrStub.last

    expect(xhr.method).toBe('POST')
    expect(xhr.url).toBe('/pimcore-studio/api/bundle/data-importer/ad-hoc-import/file')
    expect(xhr.body?.entries).toEqual([['file', file], ['folderId', '77']])

    xhr.respond(200, '{"fileId":"file-1"}')

    await expect(result).resolves.toEqual({ fileId: 'file-1' })
  })

  it('reports how far the upload is', () => {
    const onProgress = jest.fn()
    uploadFile(file, 77, onProgress)

    XhrStub.last.upload.onprogress?.({ loaded: 54, total: 84 })

    expect(onProgress).toHaveBeenCalledWith(54, 84)
  })

  it('can be aborted', async () => {
    const { result, abort } = uploadFile(file, 77, () => undefined)

    abort()

    await expect(result).rejects.toBeInstanceOf(UploadAborted)
  })

  it('fails with the answer of the server', async () => {
    const { result } = uploadFile(file, 77, () => undefined)

    XhrStub.last.respond(403, '{"message":"Access denied"}')

    await expect(result).rejects.toMatchObject({ data: { message: 'Access denied' } })
    await expect(result).rejects.toBeInstanceOf(UploadFailed)
  })

  it('fails with the text of an answer that is no JSON', async () => {
    const { result } = uploadFile(file, 77, () => undefined)

    XhrStub.last.respond(502, 'Bad Gateway')

    await expect(result).rejects.toMatchObject({ data: 'Bad Gateway' })
  })

  it('fails without data when the connection breaks', async () => {
    const { result } = uploadFile(file, 77, () => undefined)

    XhrStub.last.onerror?.()

    await expect(result).rejects.toMatchObject({ data: null })
  })
})
