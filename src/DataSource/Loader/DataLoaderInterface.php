<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader;


use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;

interface DataLoaderInterface extends SettingsAwareInterface
{

    /**
     * @return string
     */
    public function loadData(): string;

    /**
     *
     */
    public function cleanup(): void;

}
