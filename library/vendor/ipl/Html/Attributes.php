<?php

namespace dipl\Html;

use Icinga\Exception\IcingaException;
use Icinga\Exception\ProgrammingError;

class Attributes
{
    /** @var Attribute[] */
    protected $attributes = [];

    /** @var callable */
    protected $callbacks = [];

    /** @var string */
    protected $prefix = '';

    /**
     * Attributes constructor.
     * @param Attribute[] $attributes
     * @throws ProgrammingError
     */
    public function __construct(array $attributes = null)
    {
        if (empty($attributes)) {
            return;
        }

        foreach ($attributes as $key => $value) {
            if ($value instanceof Attribute) {
                $this->addAttribute($value);
            } elseif (is_string($key)) {
                $this->add($key, $value);
            } elseif (is_array($value) && count($value) === 2) {
                $this->add(array_shift($value), array_shift($value));
            }
        }
    }

    /**
     * @param Attribute[] $attributes
     * @return static
     * @throws ProgrammingError
     */
    public static function create(array $attributes = null)
    {
        return new static($attributes);
    }

    /**
     * @param Attributes|array|null $attributes
     * @return Attributes
     * @throws IcingaException
     */
    public static function wantAttributes($attributes)
    {
        if ($attributes instanceof Attributes) {
            return $attributes;
        } else {
            $self = new static();
            if (is_array($attributes)) {
                foreach ($attributes as $k => $v) {
                    $self->add($k, $v);
                }

                return $self;
            } elseif ($attributes !== null) {
                throw new IcingaException(
                    'Attributes, Array or Null expected, got %s',
                    Html::getPhpTypeName($attributes)
                );
            }
            return $self;
        }
    }

    /**
     * @return Attribute[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param Attribute|string $attribute
     * @param string|array $value
     * @return $this
     * @throws ProgrammingError
     */
    public function add($attribute, $value = null)
    {
        if ($attribute instanceof static) {
            foreach ($attribute->getAttributes() as $a) {
                $this->add($a);
            }
        } elseif ($attribute instanceof Attribute) {
            $this->addAttribute($attribute);
        } elseif (is_array($attribute)) {
            foreach ($attribute as $name => $value) {
                $this->add($name, $value);
            }
        } else {
            $this->addAttribute(Attribute::create($attribute, $value));
        }

        return $this;
    }

    /**
     * @param Attribute|array|string $attribute
     * @param string|array $value
     * @return $this
     * @throws ProgrammingError
     */
    public function set($attribute, $value = null)
    {
        if ($attribute instanceof static) {
            foreach ($attribute as $a) {
                $this->setAttribute($a);
            }

            return $this;
        } elseif ($attribute instanceof Attribute) {
            return $this->setAttribute($attribute);
        } elseif (is_array($attribute)) {
            foreach ($attribute as $name => $value) {
                $this->set($name, $value);
            }

            return $this;
        } else {
            return $this->setAttribute(new Attribute($attribute, $value));
        }
    }

    /**
     * @param $name
     * @return Attribute
     * @throws ProgrammingError
     */
    public function get($name)
    {
        if ($this->has($name)) {
            return $this->attributes[$name];
        } else {
            return Attribute::createEmpty($name);
        }
    }

    /**
     * @param $name
     * @return Attribute|false
     */
    public function remove($name)
    {
        if ($this->has($name)) {
            $attribute = $this->attributes[$name];
            unset($this->attributes[$name]);

            return $attribute;
        } else {
            return false;
        }
    }

    /**
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * @param Attribute $attribute
     * @return $this
     */
    public function addAttribute(Attribute $attribute)
    {
        $name = $attribute->getName();
        if (array_key_exists($name, $this->attributes)) {
            $this->attributes[$name]->addValue($attribute->getValue());
        } else {
            $this->attributes[$name] = $attribute;
        }

        return $this;
    }

    /**
     * @param Attribute $attribute
     * @return $this
     */
    public function setAttribute(Attribute $attribute)
    {
        $name = $attribute->getName();
        $this->attributes[$name] = $attribute;
        return $this;
    }

    /**
     * Callback must return an instance of Attribute
     *
     * @param string $name
     * @param callable $callback
     * @return $this
     * @throws ProgrammingError
     */
    public function registerAttributeCallback($name, $callback)
    {
        if (! is_callable($callback)) {
            throw new ProgrammingError(__METHOD__ . ' expects a callable callback');
        }
        $this->callbacks[$name] = $callback;

        return $this;
    }

    /**
     * @return string
     * @throws ProgrammingError
     */
    public function render()
    {
        if (empty($this->attributes) && empty($this->callbacks)) {
            return '';
        }

        $parts = [];
        foreach ($this->callbacks as $name => $callback) {
            $attribute = call_user_func($callback);
            if ($attribute instanceof Attribute) {
                $parts[] = $attribute->render();
            } elseif (is_string($attribute)) {
                $parts[] = Attribute::create($name, $attribute)->render();
            } elseif (null === $attribute) {
                continue;
            } else {
                throw new ProgrammingError(
                    'A registered attribute callback must return string, null'
                    . ' or an Attribute'
                );
            }
        }

        foreach ($this->attributes as $attribute) {
            if ($attribute->isEmpty()) {
                continue;
            }

            $parts[] = $attribute->render();
        }

        $separator = ' ' . $this->prefix;

        return $separator . implode($separator, $parts);
    }

    /**
     * @param string $prefix
     * @return $this
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }
}
