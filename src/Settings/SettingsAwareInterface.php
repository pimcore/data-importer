<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Settings;


interface SettingsAwareInterface
{
    /**
     * @param array $settings
     * @return mixed
     */
    public function setSettings(array $settings): void;
}
