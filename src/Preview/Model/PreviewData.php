<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Preview\Model;

class PreviewData
{
    /**
     * @var array
     */
    protected $labels;

    /**
     * @var array
     */
    protected $previewData;

    /**
     * @var int
     */
    protected $recordNumber;

    /**
     * @var array
     */
    protected $mappedColumns;

    /**
     * PreviewData constructor.
     *
     * @param array $labels
     * @param array $previewData
     * @param int $recordNumber
     * @param array $mappedColumns
     */
    public function __construct(array $labels, array $previewData, int $recordNumber, array $mappedColumns = [])
    {
        $this->labels = $labels;
        $this->previewData = $previewData;
        $this->recordNumber = $recordNumber;
        $this->mappedColumns = array_flip($mappedColumns);
    }

    public function getDataColumnHeaders(): array
    {
        $columnHeaders = [];
        foreach ($this->labels as $index => $label) {
            $columnHeaders[] = [
                'id' => (string) $index,
                'dataIndex' => (string) $index,
                'label' => $label
            ];
        }

        return $columnHeaders;
    }

    public function getDataPreview(): array
    {
        $dataPreview = [];

        foreach ($this->previewData as $index => $attribute) {
            $dataPreview[] = [
                'dataIndex' => (string) $index,
                'label' => $this->labels[$index] ?? $index,
                'data' => $attribute,
                'mapped' => array_key_exists((string) $index, $this->mappedColumns)
            ];
        }

        return $dataPreview;
    }

    public function getRecordNumber(): int
    {
        return $this->recordNumber;
    }

    public function getRawData(): array
    {
        return $this->previewData;
    }
}

class_alias(PreviewData::class, 'Pimcore\Bundle\DataImporterBundle\Settings\PreviewData');
