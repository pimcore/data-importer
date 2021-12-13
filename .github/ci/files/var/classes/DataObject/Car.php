<?php

/**
* Inheritance: yes
* Variants: no


Fields Summary:
- localizedfields [localizedfields]
-- name [input]
-- description [wysiwyg]
-- textsAvailable [calculatedValue]
- series [input]
- manufacturer [manyToOneRelation]
- bodyStyle [manyToOneRelation]
- carClass [select]
- productionYear [numeric]
- color [multiselect]
- country [country]
- categories [manyToManyObjectRelation]
- gallery [imageGallery]
- genericImages [imageGallery]
- attributes [objectbricks]
- saleInformation [objectbricks]
- location [geopoint]
- attributesAvailable [calculatedValue]
- saleInformationAvailable [calculatedValue]
- imagesAvailable [calculatedValue]
- objectType [select]
- urlSlug [urlSlug]
- test [classificationstore]
*/

namespace Pimcore\Model\DataObject;

use Pimcore\Model\DataObject\Exception\InheritanceParentNotFoundException;
use Pimcore\Model\DataObject\PreGetValueHookInterface;

/**
* @method static \Pimcore\Model\DataObject\Car\Listing getList()
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByLocalizedfields($field, $value, $locale = null, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByName($value, $locale = null, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByDescription($value, $locale = null, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByTextsAvailable($value, $locale = null, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getBySeries($value, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByManufacturer($value, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByBodyStyle($value, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByCarClass($value, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByProductionYear($value, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByColor($value, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByCountry($value, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByCategories($value, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByObjectType($value, $limit = 0, $offset = 0, $objectTypes = null)
* @method static \Pimcore\Model\DataObject\Car\Listing|\Pimcore\Model\DataObject\Car|null getByUrlSlug($value, $limit = 0, $offset = 0, $objectTypes = null)
*/

class Car extends Concrete
{
protected $o_classId = "CAR";
protected $o_className = "Car";
protected $localizedfields;
protected $series;
protected $manufacturer;
protected $bodyStyle;
protected $carClass;
protected $productionYear;
protected $color;
protected $country;
protected $categories;
protected $gallery;
protected $genericImages;
protected $attributes;
protected $saleInformation;
protected $location;
protected $objectType;
protected $urlSlug;
protected $test;


/**
* @param array $values
* @return \Pimcore\Model\DataObject\Car
*/
public static function create($values = array()) {
	$object = new static();
	$object->setValues($values);
	return $object;
}

/**
* Get localizedfields -
* @return \Pimcore\Model\DataObject\Localizedfield|null
*/
public function getLocalizedfields(): ?\Pimcore\Model\DataObject\Localizedfield
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("localizedfields");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->getClass()->getFieldDefinition("localizedfields")->preGetData($this);

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("localizedfields")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("localizedfields");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Get name - Name
* @return string|null
*/
public function getName($language = null): ?string
{
	$data = $this->getLocalizedfields()->getLocalizedValue("name", $language);
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("name");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Get description - Description
* @return string|null
*/
public function getDescription($language = null): ?string
{
	$data = $this->getLocalizedfields()->getLocalizedValue("description", $language);
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("description");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Get textsAvailable - Texts Available
* @return \Pimcore\Model\DataObject\Data\CalculatedValue|null
*/
public function getTextsAvailable($language = null)
{
	if (!$language) {
		try {
			$locale = \Pimcore::getContainer()->get("pimcore.locale")->getLocale();
			if (\Pimcore\Tool::isValidLanguage($locale)) {
				$language = (string) $locale;
			} else {
				throw new \Exception("Not supported language");
			}
		} catch (\Exception $e) {
			$language = \Pimcore\Tool::getDefaultLanguage();
		}
	}
	$object = $this;
	$fieldDefinition = $this->getClass()->getFieldDefinition("localizedfields")->getFieldDefinition("textsAvailable");
	$data = new \Pimcore\Model\DataObject\Data\CalculatedValue('textsAvailable');
	$data->setContextualData("localizedfield", "localizedfields", null, $language, null, null, $fieldDefinition);
	$data = \Pimcore\Model\DataObject\Service::getCalculatedFieldValue($object, $data);
	return $data;
}

/**
* Set localizedfields -
* @param \Pimcore\Model\DataObject\Localizedfield|null $localizedfields
* @return \Pimcore\Model\DataObject\Car
*/
public function setLocalizedfields(?\Pimcore\Model\DataObject\Localizedfield $localizedfields)
{
	$inheritValues = self::getGetInheritedValues();
	self::setGetInheritedValues(false);
	$hideUnpublished = \Pimcore\Model\DataObject\Concrete::getHideUnpublished();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished(false);
	$currentData = $this->getLocalizedfields();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished($hideUnpublished);
	self::setGetInheritedValues($inheritValues);
	$this->markFieldDirty("localizedfields", true);
	$this->localizedfields = $localizedfields;

	return $this;
}

/**
* Set name - Name
* @param string|null $name
* @return \Pimcore\Model\DataObject\Car
*/
public function setName (?string $name, $language = null)
{
	$isEqual = false;
	$this->getLocalizedfields()->setLocalizedValue("name", $name, $language, !$isEqual);

	return $this;
}

/**
* Set description - Description
* @param string|null $description
* @return \Pimcore\Model\DataObject\Car
*/
public function setDescription (?string $description, $language = null)
{
	$isEqual = false;
	$this->getLocalizedfields()->setLocalizedValue("description", $description, $language, !$isEqual);

	return $this;
}

/**
* Set textsAvailable - Texts Available
* @param \Pimcore\Model\DataObject\Data\CalculatedValue|null $textsAvailable
* @return \Pimcore\Model\DataObject\Car
*/
public function setTextsAvailable($textsAvailable, $language = null)
{
	return $this;
}

/**
* Get series - Series
* @return string|null
*/
public function getSeries(): ?string
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("series");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->series;

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("series")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("series");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set series - Series
* @param string|null $series
* @return \Pimcore\Model\DataObject\Car
*/
public function setSeries(?string $series)
{
	$this->series = $series;

	return $this;
}

/**
* Get manufacturer - Manufacturer
* @return \Pimcore\Model\DataObject\Manufacturer|null
*/
public function getManufacturer(): ?\Pimcore\Model\Element\AbstractElement
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("manufacturer");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->getClass()->getFieldDefinition("manufacturer")->preGetData($this);

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("manufacturer")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("manufacturer");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set manufacturer - Manufacturer
* @param \Pimcore\Model\DataObject\Manufacturer $manufacturer
* @return \Pimcore\Model\DataObject\Car
*/
public function setManufacturer(?\Pimcore\Model\Element\AbstractElement $manufacturer)
{
	/** @var \Pimcore\Model\DataObject\ClassDefinition\Data\ManyToOneRelation $fd */
	$fd = $this->getClass()->getFieldDefinition("manufacturer");
	$inheritValues = self::getGetInheritedValues();
	self::setGetInheritedValues(false);
	$hideUnpublished = \Pimcore\Model\DataObject\Concrete::getHideUnpublished();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished(false);
	$currentData = $this->getManufacturer();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished($hideUnpublished);
	self::setGetInheritedValues($inheritValues);
	$isEqual = $fd->isEqual($currentData, $manufacturer);
	if (!$isEqual) {
		$this->markFieldDirty("manufacturer", true);
	}
	$this->manufacturer = $fd->preSetData($this, $manufacturer);
	return $this;
}

/**
* Get bodyStyle - Body Style
* @return \Pimcore\Model\DataObject\BodyStyle|null
*/
public function getBodyStyle(): ?\Pimcore\Model\Element\AbstractElement
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("bodyStyle");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->getClass()->getFieldDefinition("bodyStyle")->preGetData($this);

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("bodyStyle")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("bodyStyle");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set bodyStyle - Body Style
* @param \Pimcore\Model\DataObject\BodyStyle $bodyStyle
* @return \Pimcore\Model\DataObject\Car
*/
public function setBodyStyle(?\Pimcore\Model\Element\AbstractElement $bodyStyle)
{
	/** @var \Pimcore\Model\DataObject\ClassDefinition\Data\ManyToOneRelation $fd */
	$fd = $this->getClass()->getFieldDefinition("bodyStyle");
	$inheritValues = self::getGetInheritedValues();
	self::setGetInheritedValues(false);
	$hideUnpublished = \Pimcore\Model\DataObject\Concrete::getHideUnpublished();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished(false);
	$currentData = $this->getBodyStyle();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished($hideUnpublished);
	self::setGetInheritedValues($inheritValues);
	$isEqual = $fd->isEqual($currentData, $bodyStyle);
	if (!$isEqual) {
		$this->markFieldDirty("bodyStyle", true);
	}
	$this->bodyStyle = $fd->preSetData($this, $bodyStyle);
	return $this;
}

/**
* Get carClass - Class
* @return string|null
*/
public function getCarClass(): ?string
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("carClass");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->carClass;

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("carClass")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("carClass");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set carClass - Class
* @param string|null $carClass
* @return \Pimcore\Model\DataObject\Car
*/
public function setCarClass(?string $carClass)
{
	$this->carClass = $carClass;

	return $this;
}

/**
* Get productionYear - Production Year
* @return int|null
*/
public function getProductionYear(): ?int
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("productionYear");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->productionYear;

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("productionYear")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("productionYear");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set productionYear - Production Year
* @param int|null $productionYear
* @return \Pimcore\Model\DataObject\Car
*/
public function setProductionYear(?int $productionYear)
{
	/** @var \Pimcore\Model\DataObject\ClassDefinition\Data\Numeric $fd */
	$fd = $this->getClass()->getFieldDefinition("productionYear");
	$this->productionYear = $fd->preSetData($this, $productionYear);
	return $this;
}

/**
* Get color - Color
* @return string[]|null
*/
public function getColor(): ?array
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("color");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->color;

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("color")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("color");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set color - Color
* @param string[]|null $color
* @return \Pimcore\Model\DataObject\Car
*/
public function setColor(?array $color)
{
	$this->color = $color;

	return $this;
}

/**
* Get country - Country
* @return string|null
*/
public function getCountry(): ?string
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("country");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->country;

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("country")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("country");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set country - Country
* @param string|null $country
* @return \Pimcore\Model\DataObject\Car
*/
public function setCountry(?string $country)
{
	$this->country = $country;

	return $this;
}

/**
* Get categories - Categories
* @return \Pimcore\Model\DataObject\Category[]
*/
public function getCategories(): array
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("categories");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->getClass()->getFieldDefinition("categories")->preGetData($this);

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("categories")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("categories");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set categories - Categories
* @param \Pimcore\Model\DataObject\Category[] $categories
* @return \Pimcore\Model\DataObject\Car
*/
public function setCategories(?array $categories)
{
	/** @var \Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation $fd */
	$fd = $this->getClass()->getFieldDefinition("categories");
	$inheritValues = self::getGetInheritedValues();
	self::setGetInheritedValues(false);
	$hideUnpublished = \Pimcore\Model\DataObject\Concrete::getHideUnpublished();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished(false);
	$currentData = $this->getCategories();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished($hideUnpublished);
	self::setGetInheritedValues($inheritValues);
	$isEqual = $fd->isEqual($currentData, $categories);
	if (!$isEqual) {
		$this->markFieldDirty("categories", true);
	}
	$this->categories = $fd->preSetData($this, $categories);
	return $this;
}

/**
* Get gallery - Gallery
* @return \Pimcore\Model\DataObject\Data\ImageGallery|null
*/
public function getGallery(): ?\Pimcore\Model\DataObject\Data\ImageGallery
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("gallery");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->gallery;

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("gallery")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("gallery");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set gallery - Gallery
* @param \Pimcore\Model\DataObject\Data\ImageGallery|null $gallery
* @return \Pimcore\Model\DataObject\Car
*/
public function setGallery(?\Pimcore\Model\DataObject\Data\ImageGallery $gallery)
{
	$this->gallery = $gallery;

	return $this;
}

/**
* Get genericImages - Generic Images
* @return \Pimcore\Model\DataObject\Data\ImageGallery|null
*/
public function getGenericImages(): ?\Pimcore\Model\DataObject\Data\ImageGallery
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("genericImages");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->genericImages;

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("genericImages")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("genericImages");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set genericImages - Generic Images
* @param \Pimcore\Model\DataObject\Data\ImageGallery|null $genericImages
* @return \Pimcore\Model\DataObject\Car
*/
public function setGenericImages(?\Pimcore\Model\DataObject\Data\ImageGallery $genericImages)
{
	$this->genericImages = $genericImages;

	return $this;
}

/**
* @return \Pimcore\Model\DataObject\Car\Attributes
*/
public function getAttributes(): ?\Pimcore\Model\DataObject\Objectbrick
{
	$data = $this->attributes;
	if (!$data) {
		if (\Pimcore\Tool::classExists("\\Pimcore\\Model\\DataObject\\Car\\Attributes")) {
			$data = new \Pimcore\Model\DataObject\Car\Attributes($this, "attributes");
			$this->attributes = $data;
		} else {
			return null;
		}
	}
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("attributes");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	return $data;
}

/**
* Set attributes - Attributes
* @param \Pimcore\Model\DataObject\Objectbrick|null $attributes
* @return \Pimcore\Model\DataObject\Car
*/
public function setAttributes(?\Pimcore\Model\DataObject\Objectbrick $attributes)
{
	/** @var \Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks $fd */
	$fd = $this->getClass()->getFieldDefinition("attributes");
	$this->attributes = $fd->preSetData($this, $attributes);
	return $this;
}

/**
* @return \Pimcore\Model\DataObject\Car\SaleInformation
*/
public function getSaleInformation(): ?\Pimcore\Model\DataObject\Objectbrick
{
	$data = $this->saleInformation;
	if (!$data) {
		if (\Pimcore\Tool::classExists("\\Pimcore\\Model\\DataObject\\Car\\SaleInformation")) {
			$data = new \Pimcore\Model\DataObject\Car\SaleInformation($this, "saleInformation");
			$this->saleInformation = $data;
		} else {
			return null;
		}
	}
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("saleInformation");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	return $data;
}

/**
* Set saleInformation - Sale Information
* @param \Pimcore\Model\DataObject\Objectbrick|null $saleInformation
* @return \Pimcore\Model\DataObject\Car
*/
public function setSaleInformation(?\Pimcore\Model\DataObject\Objectbrick $saleInformation)
{
	/** @var \Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks $fd */
	$fd = $this->getClass()->getFieldDefinition("saleInformation");
	$this->saleInformation = $fd->preSetData($this, $saleInformation);
	return $this;
}

/**
* Get location - Location
* @return \Pimcore\Model\DataObject\Data\GeoCoordinates|null
*/
public function getLocation(): ?\Pimcore\Model\DataObject\Data\GeoCoordinates
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("location");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->location;

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("location")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("location");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set location - Location
* @param \Pimcore\Model\DataObject\Data\GeoCoordinates|null $location
* @return \Pimcore\Model\DataObject\Car
*/
public function setLocation(?\Pimcore\Model\DataObject\Data\GeoCoordinates $location)
{
	$this->location = $location;

	return $this;
}

/**
* Get attributesAvailable - Attributes Available
* @return \Pimcore\Model\DataObject\Data\CalculatedValue|null
*/
public function getAttributesAvailable()
{
	$data = new \Pimcore\Model\DataObject\Data\CalculatedValue('attributesAvailable');
	$data->setContextualData("object", null, null, null);
	$object = $this;
	$data = \Pimcore\Model\DataObject\Service::getCalculatedFieldValue($object, $data);

	return $data;
}

/**
* Set attributesAvailable - Attributes Available
* @param \Pimcore\Model\DataObject\Data\CalculatedValue|null $attributesAvailable
* @return \Pimcore\Model\DataObject\Car
*/
public function setAttributesAvailable($attributesAvailable)
{
	return $this;
}

/**
* Get saleInformationAvailable - Sale Information Available
* @return \Pimcore\Model\DataObject\Data\CalculatedValue|null
*/
public function getSaleInformationAvailable()
{
	$data = new \Pimcore\Model\DataObject\Data\CalculatedValue('saleInformationAvailable');
	$data->setContextualData("object", null, null, null);
	$object = $this;
	$data = \Pimcore\Model\DataObject\Service::getCalculatedFieldValue($object, $data);

	return $data;
}

/**
* Set saleInformationAvailable - Sale Information Available
* @param \Pimcore\Model\DataObject\Data\CalculatedValue|null $saleInformationAvailable
* @return \Pimcore\Model\DataObject\Car
*/
public function setSaleInformationAvailable($saleInformationAvailable)
{
	return $this;
}

/**
* Get imagesAvailable - Images Available
* @return \Pimcore\Model\DataObject\Data\CalculatedValue|null
*/
public function getImagesAvailable()
{
	$data = new \Pimcore\Model\DataObject\Data\CalculatedValue('imagesAvailable');
	$data->setContextualData("object", null, null, null);
	$object = $this;
	$data = \Pimcore\Model\DataObject\Service::getCalculatedFieldValue($object, $data);

	return $data;
}

/**
* Set imagesAvailable - Images Available
* @param \Pimcore\Model\DataObject\Data\CalculatedValue|null $imagesAvailable
* @return \Pimcore\Model\DataObject\Car
*/
public function setImagesAvailable($imagesAvailable)
{
	return $this;
}

/**
* Get objectType - Object Type
* @return string|null
*/
public function getObjectType(): ?string
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("objectType");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->objectType;

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("objectType")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("objectType");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set objectType - Object Type
* @param string|null $objectType
* @return \Pimcore\Model\DataObject\Car
*/
public function setObjectType(?string $objectType)
{
	$this->objectType = $objectType;

	return $this;
}

/**
* Get urlSlug - UrlSlug
* @return \Pimcore\Model\DataObject\Data\UrlSlug[]
*/
public function getUrlSlug(): ?array
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("urlSlug");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->getClass()->getFieldDefinition("urlSlug")->preGetData($this);

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set urlSlug - UrlSlug
* @param \Pimcore\Model\DataObject\Data\UrlSlug[] $urlSlug
* @return \Pimcore\Model\DataObject\Car
*/
public function setUrlSlug(?array $urlSlug)
{
	/** @var \Pimcore\Model\DataObject\ClassDefinition\Data\UrlSlug $fd */
	$fd = $this->getClass()->getFieldDefinition("urlSlug");
	$inheritValues = self::getGetInheritedValues();
	self::setGetInheritedValues(false);
	$hideUnpublished = \Pimcore\Model\DataObject\Concrete::getHideUnpublished();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished(false);
	$currentData = $this->getUrlSlug();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished($hideUnpublished);
	self::setGetInheritedValues($inheritValues);
	$isEqual = $fd->isEqual($currentData, $urlSlug);
	if (!$isEqual) {
		$this->markFieldDirty("urlSlug", true);
	}
	$this->urlSlug = $fd->preSetData($this, $urlSlug);
	return $this;
}

/**
* Get test - test
* @return \Pimcore\Model\DataObject\Classificationstore|null
*/
public function getTest(): ?\Pimcore\Model\DataObject\Classificationstore
{
	if ($this instanceof PreGetValueHookInterface && !\Pimcore::inAdmin()) {
		$preValue = $this->preGetValue("test");
		if ($preValue !== null) {
			return $preValue;
		}
	}

	$data = $this->getClass()->getFieldDefinition("test")->preGetData($this);

	if (\Pimcore\Model\DataObject::doGetInheritedValues() && $this->getClass()->getFieldDefinition("test")->isEmpty($data)) {
		try {
			return $this->getValueFromParent("test");
		} catch (InheritanceParentNotFoundException $e) {
			// no data from parent available, continue ...
		}
	}

	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set test - test
* @param \Pimcore\Model\DataObject\Classificationstore|null $test
* @return \Pimcore\Model\DataObject\Car
*/
public function setTest(?\Pimcore\Model\DataObject\Classificationstore $test)
{
	$this->test = $test;

	return $this;
}

}

