<?php

namespace Icinga\Module\Director\Field;

use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\IcingaObject;

class FieldSpec
{
    /** @var string */
    protected $varName;

    /** @var string */
    protected $category;

    /** @var string */
    protected $caption;

    /** @var boolean */
    protected $isRequired = false;

    /** @var string */
    protected $description;

    /** @var string */
    protected $dataType;

    /** @var string */
    protected $varFilter;

    /** @var string */
    protected $format = "string";

    /**
     * FieldSpec constructor.
     * @param $dataType
     * @param $varName
     * @param $caption
     */
    public function __construct($dataType, $varName, $caption)
    {
        $this->dataType = $dataType;
        $this->varName = $varName;
        $this->caption = $caption;
    }

    public function toDataField(IcingaObject $object)
    {
        return DirectorDatafield::create([
            'varname'     => $this->getVarName(),
            'category'    => $this->getCategory(),
            'caption'     => $this->getCaption(),
            'description' => $this->getDescription(),
            'datatype'    => $this->getDataType(),
            'format'      => $this->getFormat(),
            'var_filter'  => $this->getVarFilter(),
            'icinga_type' => $object->getShortTableName(),
            'object_id'   => $object->get('id'),
        ]);
    }

    /**
     * @return string
     */
    public function getVarName()
    {
        return $this->varName;
    }

    /**
     * @param string $varName
     * @return FieldSpec
     */
    public function setVarName($varName)
    {
        $this->varName = $varName;
        return $this;
    }

    /**
     * @return string
     */
    public function getCaption()
    {
        return $this->caption;
    }

    /**
     * @param string $caption
     * @return FieldSpec
     */
    public function setCaption($caption)
    {
        $this->caption = $caption;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRequired()
    {
        return $this->isRequired;
    }

    /**
     * @param bool $isRequired
     * @return FieldSpec
     */
    public function setIsRequired($isRequired)
    {
        $this->isRequired = $isRequired;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return FieldSpec
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDataType()
    {
        return $this->dataType;
    }

    /**
     * @param string $dataType
     * @return FieldSpec
     */
    public function setDataType($dataType)
    {
        $this->dataType = $dataType;
        return $this;
    }

    /**
     * @return string
     */
    public function getVarFilter()
    {
        return $this->varFilter;
    }

    /**
     * @param string $varFilter
     * @return FieldSpec
     */
    public function setVarFilter($varFilter)
    {
        $this->varFilter = $varFilter;
        return $this;
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @param string $format
     * @return FieldSpec
     */
    public function setFormat($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param string $category
     * @return FieldSpec
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }
}
