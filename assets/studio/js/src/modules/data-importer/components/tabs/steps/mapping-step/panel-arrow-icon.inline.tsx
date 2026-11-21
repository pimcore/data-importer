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

export const PanelArrowIcon = (): React.JSX.Element => (
  <svg
    fill="none"
    height="38"
    viewBox="0 0 38 38"
    width="38"
    xmlns="http://www.w3.org/2000/svg"
  >
    <g clipPath="url(#panel-arrow-clip)">
      <path
        d="M26.9167 12.6641L33.25 18.9974L26.9167 25.3307"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="2"
      />
      <path
        d="M33.25 19L24.7095 19C22.9533 19.0001 21.2242 18.5666 19.6758 17.738C18.1274 16.9094 16.8075 15.7113 15.8333 14.25C14.8592 12.7887 13.5393 11.5906 11.9909 10.762C10.4424 9.93337 8.71337 9.49988 6.95717 9.5L4.75 9.5"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="2"
      />
      <path
        d="M33.25 19L24.7095 19C22.9533 18.9999 21.2242 19.4334 19.6758 20.262C18.1274 21.0906 16.8075 22.2887 15.8333 23.75C14.8592 25.2113 13.5393 26.4094 11.9909 27.238C10.4424 28.0666 8.71337 28.5001 6.95717 28.5L4.75 28.5"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="2"
      />
    </g>
    <defs>
      <clipPath id="panel-arrow-clip">
        <rect
          fill="white"
          height="38"
          transform="translate(38 1.66103e-06) rotate(90)"
          width="38"
        />
      </clipPath>
    </defs>
  </svg>
)
