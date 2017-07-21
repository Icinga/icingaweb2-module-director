<?php

namespace ipl\Html;

use Icinga\Exception\IcingaException;
use Icinga\Exception\ProgrammingError;

class Attributes
{
    /** @var Attribute[] */
    protected $attributes = array();

    /** @var callable */
    protected $callbacks = array();

    /**
     * Attributes constructor.
     * @param Attribute[] $attributes
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
                    Util::getPhpTypeName($attributes)
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
     * @param Attribute|string $attribute
     * @param string|array $value
     * @return $this
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
        } else {
            return $this->setAttribute(new Attribute($attribute, $value));
        }
    }

    /**
     * @param $name
     * @return Attribute
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
     * @return bool
     */
    public function delete($name)
    {
        if ($this->has($name)) {
            unset($this->attributes[$name]);
            return true;
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
    public function registerCallbackFor($name, $callback)
    {
        if (! is_callable($callback)) {
            throw new ProgrammingError('registerCallBack expects a callable callback');
        }
        $this->callbacks[$name] = $callback;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        if (empty($this->attributes) && empty($this->callbacks)) {
            return '';
        }

        $parts = array();
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
        return ' ' . implode(' ', $parts);
    }
}
