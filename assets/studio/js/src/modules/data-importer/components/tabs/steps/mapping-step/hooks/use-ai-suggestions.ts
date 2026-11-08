/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useEffect } from 'react'
import {
  type BundleDataImporterMappingSuggestionsResponse,
  useBundleDataImporterMappingSuggestionLoadAiQuery
} from '../../../../../data-importer-api-slice-enhanced'

const POLLING_INTERVAL_MS = 3000

/**
 * Follows the AI stage of a suggestion request until it ends. A stage that fails hands over no suggestions: those of
 * the name comparison stay as they are.
 *
 * @returns whether the stage is still running
 */
export function useAiSuggestions (
  requestId: string | null,
  onSettled: (suggestions: BundleDataImporterMappingSuggestionsResponse['suggestions']) => void
): boolean {
  const { currentData, isError } = useBundleDataImporterMappingSuggestionLoadAiQuery(
    { requestId: requestId ?? '' },
    { skip: requestId === null, pollingInterval: POLLING_INTERVAL_MS }
  )

  useEffect(() => {
    if (requestId === null) return

    if (isError || currentData?.aiStatus === 'failed') {
      onSettled([])
    } else if (currentData?.aiStatus === 'finished') {
      onSettled(currentData.suggestions)
    }
  }, [requestId, currentData, isError, onSettled])

  return requestId !== null
}
