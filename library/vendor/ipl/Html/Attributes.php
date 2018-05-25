<?php

namespace dipl\Html;

use InvalidArgumentException;

class Attributes
{
    /** @var Attribute[] */
    protected $attributes = [];

    /** @var callable[] */
    protected $callbacks = [];

    /** @var callable[] */
    protected $setterCallbacks = [];

    /** @var string */
    protected $prefix = '';

    /**
     * Attributes constructor.
     * @param Attribute[] $attributes
     * @throws InvalidArgumentException
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
     * @throws InvalidArgumentException
     */
    public static function create(array $attributes = null)
    {
        return new static($attributes);
    }

    /**
     * @param Attributes|array|null $attributes
     * @return Attributes
     * @throws InvalidArgumentException
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
                throw new InvalidArgumentException(sprintf(
                    'Attributes, Array or Null expected, got %s',
                    Html::getPhpTypeName($attributes)
                ));
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
     * @throws InvalidArgumentException
     */
    public function add($attribute, $value = null)
    {
        // TODO: do not allow Attribute and Attributes
        if ($attribute instanceof Attributes) {
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
            if (array_key_exists($attribute, $this->setterCallbacks)) {
                $callback = $this->setterCallbacks[$attribute];
                $callback($value);
            } else {
                $this->addAttribute(Attribute::create($attribute, $value));
            }
        }

        return $this;
    }

    /**
     * @param Attribute|array|string $attribute
     * @param string|array $value
     * @return $this
     * @throws InvalidArgumentException
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
            if (array_key_exists($attribute, $this->setterCallbacks)) {
                $callback = $this->setterCallbacks[$attribute];
                $callback($value);

                return $this;
            } else {
                return $this->setAttribute(new Attribute($attribute, $value));
            }
        }
    }

    /**
     * @param $name
     * @return Attribute
     * @throws InvalidArgumentException
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
     * TODO: setCallback
     *
     * @param string $name
     * @param callable $callback
     * @param callable $setterCallback
     * @return $this
     * @throws InvalidArgumentException
     */
    public function registerAttributeCallback($name, $callback, $setterCallback = null)
    {
        if ($callback !== null) {
            if (! is_callable($callback)) {
                throw new InvalidArgumentException(__METHOD__ . ' expects a callable callback');
            }
            $this->callbacks[$name] = $callback;
        }

        if ($setterCallback !== null) {
            if (! is_callable($setterCallback)) {
                throw new InvalidArgumentException(__METHOD__ . ' expects a callable setterCallback');
            }
            $this->setterCallbacks[$name] = $setterCallback;
        }

        return $this;
    }

    /**
     * @return string
     * @throws InvalidArgumentException
     */
    public function render()
    {
        $parts = [];
        foreach ($this->callbacks as $name => $callback) {
            $attribute = call_user_func($callback);
            if ($attribute instanceof Attribute) {
                if ($attribute->getValue() !== null) {
                    $parts[] = $attribute->render();
                }
            } elseif (is_string($attribute)) {
                $parts[] = Attribute::create($name, $attribute)->render();
            } elseif (null === $attribute) {
                continue;
            } else {
                throw new InvalidArgumentException(
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

        if (empty($parts)) {
            return '';
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
