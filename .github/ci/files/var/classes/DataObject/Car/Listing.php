<?php

namespace Pimcore\Model\DataObject\Car;

use Pimcore\Model\DataObject;

/**
 * @method DataObject\Car current()
 * @method DataObject\Car[] load()
 * @method DataObject\Car[] getData()
 */

class Listing extends DataObject\Listing\Concrete
{
protected $classId = "CAR";
protected $className = "Car";


/**
* Filter by name (Name)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByName ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("name")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by description (Description)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByDescription ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("description")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by textsAvailable (Texts Available)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByTextsAvailable ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("textsAvailable")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by series (Series)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterBySeries ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("series")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by manufacturer (Manufacturer)
* @param mixed $data
* @param string $operator SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByManufacturer ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("manufacturer")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by bodyStyle (Body Style)
* @param mixed $data
* @param string $operator SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByBodyStyle ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("bodyStyle")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by carClass (Class)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByCarClass ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("carClass")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by productionYear (Production Year)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByProductionYear ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("productionYear")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by color (Color)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByColor ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("color")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by country (Country)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByCountry ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("country")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by categories (Categories)
* @param mixed $data
* @param string $operator SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByCategories ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("categories")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by objectType (Object Type)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByObjectType ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("objectType")->addListingFilter($this, $data, $operator);
	return $this;
}

/**
* Filter by urlSlug (UrlSlug)
* @param string|int|float|array|Model\Element\ElementInterface $data  comparison data, can be scalar or array (if operator is e.g. "IN (?)")
* @param string $operator  SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
* @return static
*/
public function filterByUrlSlug ($data, $operator = '=')
{
	$this->getClass()->getFieldDefinition("urlSlug")->addListingFilter($this, $data, $operator);
	return $this;
}



}
