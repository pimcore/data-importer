<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\DataTarget;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\Element\ElementInterface;

class Direct implements DataTargetInterface
{

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $language;

    public function setSettings(array $settings): void
    {
        if(empty($settings['fieldName'])) {
            throw new InvalidConfigurationException('Empty field name.');
        }

        $this->fieldName = $settings['fieldName'];
        $this->language = $settings['language'] ?? null;
    }


    public function assignData(ElementInterface $element, $data)
    {
        $setter = 'set' . ucfirst($this->fieldName);
        $element->$setter($data, $this->language);
    }

}
