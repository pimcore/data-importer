/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { useMemo } from "react";
import { useSettings } from "@pimcore/studio-ui-bundle/modules/app";

export function useLanguageOptions() {
    const { validLanguages } = useSettings();
    return useMemo(
        () =>
            (validLanguages ?? []).map((locale: string) => ({
                value: locale,
                label: locale,
            })),
        [validLanguages],
    );
}
