<?php

namespace ipl\Html;

use Icinga\Exception\ProgrammingError;

/**
 * HTML Attribute
 *
 * Every single HTML attribute is (or should be) an instance of this class.
 * This guarantees that every attribute is safe and escaped correctly.
 *
 * Usually attributes are not instantiated directly, but created through an HTML
 * element's exposed methods.
 *
 */
class Attribute
{
    /** @var string */
    protected $name;

    /** @var string|array */
    protected $value;

    /**
     * Attribute constructor.
     *
     * @param $name
     * @param $value
     */
    public function __construct($name, $value)
    {
        $this->setName($name)->setValue($value);
    }

    /**
     * @param $name
     * @param $value
     * @return static
     */
    public static function create($name, $value)
    {
        return new static($name, $value);
    }

    /**
     * @param $name
     * @return $this
     * @throws ProgrammingError
     */
    public function setName($name)
    {
        if (! preg_match('/^[a-z][a-z-]*$/i', $name)) {
            throw new ProgrammingError(
                'Attribute names with special characters are not yet allowed: %s',
                $name
            );
        }
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string|array $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function addValue($value)
    {
        if (! is_array($this->value)) {
            $this->value = array($this->value);
        }

        if (is_array($value)) {
            $this->value = array_merge($this->value, $value);
        } else {
            $this->value[] = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function render()
    {
        return sprintf(
            '%s="%s"',
            $this->renderName(),
            $this->renderValue()
        );
    }

    /**
     * @return string
     */
    public function renderName()
    {
        return static::escapeName($this->name);
    }

    /**
     * @return string
     */
    public function renderValue()
    {
        return static::escapeValue($this->value);
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return null === $this->value || $this->value === [];
    }

    /**
     * @param $name
     * @return static
     */
    public static function createEmpty($name)
    {
        return new static($name, null);
    }

    /**
     * @param $name
     * @return string
     */
    public static function escapeName($name)
    {
        // TODO: escape
        return (string) $name;
    }

    /**
     * @param $value
     * @return string
     */
    public static function escapeValue($value)
    {
        // TODO: escape differently
        if (is_array($value)) {
            return Util::escapeForHtml(implode(' ', $value));
        } else {
            return Util::escapeForHtml((string) $value);
        }
    }
}
