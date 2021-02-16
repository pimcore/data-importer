<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\DataTarget;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\Element\ElementInterface;

class Classificationstore implements DataTargetInterface
{

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $language;

    /**
     * @var int
     */
    protected $keyId;

    /**
     * @var int
     */
    protected $groupId;

    public function setSettings(array $settings): void
    {
        if(empty($settings['fieldName'])) {
            throw new InvalidConfigurationException('Empty field name.');
        }

        $keyParts = explode('-', ($settings['keyId'] ?? []));
        if(empty($keyParts[0]) || empty($keyParts[1])) {
            throw new InvalidConfigurationException('Empty or invalid keyId.');
        }

        $this->fieldName = $settings['fieldName'];
        $this->groupId = $keyParts[0];
        $this->keyId = $keyParts[1];
        $this->language = $settings['language'] ?? null;
    }


    public function assignData(ElementInterface $element, $data)
    {
        $getter = 'get' . ucfirst($this->fieldName);
        $classificationStore = $element->$getter();

        if($classificationStore instanceof \Pimcore\Model\DataObject\Classificationstore) {

            $classificationStore->setLocalizedKeyValue($this->groupId, $this->keyId, $data, $this->language);
            $classificationStore->setActiveGroups($classificationStore->getActiveGroups() + [$this->groupId => true]);

        } else {
            throw new InvalidConfigurationException('Field ' . $this->fieldName . ' is not a classification store.');
        }

    }

}

