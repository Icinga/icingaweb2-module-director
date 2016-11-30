<?php

/**
 * This file ...
 *
 * @copyright  Icinga Team <team@icinga.org>
 * @license  GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
 */
namespace Icinga\Module\Director\Data\Db;

use Icinga\Exception\IcingaException as IE;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Util;
use Exception;
use Zend_Db_Adapter_Abstract;

/**
 * Base class for ...
 */
abstract class DbObject
{
    /** @var DbConnection $connection */
    protected $connection;

    /** @var string Table name. MUST be set when extending this class */
    protected $table;

    /** @var Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var  Zend_Db_Adapter_Abstract */
    protected $r;

    /**
     * Default columns. MUST be set when extending this class. Each table
     * column MUST be defined with a default value. Default value may be null.
     *
     * @var array
     */
    protected $defaultProperties;

    /**
     * Properties as loaded from db
     */
    protected $loadedProperties;

    /**
     * Whether at least one property has been modified
     */
    protected $hasBeenModified = false;

    /**
     * Whether this object has been loaded from db
     */
    protected $loadedFromDb = false;

    /**
     * Object properties
     */
    protected $properties = array();

    /**
     * Property names that have been modified since object creation
     */
    protected $modifiedProperties = array();

    /**
     * Unique key name, could be primary
     */
    protected $keyName;

    /**
     * Set this to an eventual autoincrementing column. May equal $keyName
     */
    protected $autoincKeyName;

    /**
     * Filled with object instances when prefetchAll is used
     */
    protected static $prefetched = array();

    /**
     * object_name => id map for prefetched objects
     */
    protected static $prefetchedNames = array();

    protected static $prefetchStats = array();

    /**
     * Constructor is not accessible and should not be overridden
     */
    protected function __construct()
    {
        if ($this->table === null
            || $this->keyName === null
            || $this->defaultProperties === null
        ) {
            throw new IE("Someone extending this class didn't RTFM");
        }

        $this->properties = $this->defaultProperties;
        $this->beforeInit();
    }

    public function getTableName()
    {
        return $this->table;
    }

    /**
     * Kann überschrieben werden, um Kreuz-Checks usw vor dem Speichern durch-
     * zuführen - die Funktion ist aber public und erlaubt jederzeit, die Kon-
     * sistenz eines Objektes bei bedarf zu überprüfen.
     *
     * @return boolean  Ob der Wert gültig ist
     */
    public function validate()
    {
        return true;
    }

    /************************************************************************\
     * Nachfolgend finden sich ein paar Hooks, die bei Bedarf überschrieben *
     * werden können. Wann immer möglich soll darauf verzichtet werden,     *
     * andere Funktionen (wie z.B. store()) zu überschreiben.               *
    \************************************************************************/

    /**
     * Wird ausgeführt, bevor die eigentlichen Initialisierungsoperationen
     * (laden von Datenbank, aus Array etc) starten
     *
     * @return void
     */
    protected function beforeInit()
    {
    }

    /**
     * Wird ausgeführt, nachdem mittels ::factory() ein neues Objekt erstellt
     * worden ist.
     *
     * @return void
     */
    protected function onFactory()
    {
    }

    /**
     * Wird ausgeführt, nachdem mittels ::factory() ein neues Objekt erstellt
     * worden ist.
     *
     * @return void
     */
    protected function onLoadFromDb()
    {
    }

    /**
     * Wird ausgeführt, bevor ein Objekt abgespeichert wird. Die Operation
     * wird aber auf jeden Fall durchgeführt, außer man wirft eine Exception
     *
     * @return void
     */
    protected function beforeStore()
    {
    }

    /**
     * Wird ausgeführt, nachdem ein Objekt erfolgreich gespeichert worden ist
     *
     * @return void
     */
    protected function onStore()
    {
    }

    /**
     * Wird ausgeführt, nachdem ein Objekt erfolgreich der Datenbank hinzu-
     * gefügt worden ist
     *
     * @return void
     */
    protected function onInsert()
    {
    }

    /**
     * Wird ausgeführt, nachdem bestehendes Objekt erfolgreich der Datenbank
     * geändert worden ist
     *
     * @return void
     */
    protected function onUpdate()
    {
    }

    /**
     * Wird ausgeführt, bevor ein Objekt gelöscht wird. Die Operation wird
     * aber auf jeden Fall durchgeführt, außer man wirft eine Exception
     *
     * @return void
     */
    protected function beforeDelete()
    {
    }

    /**
     * Wird ausgeführt, nachdem bestehendes Objekt erfolgreich aud der
     * Datenbank gelöscht worden ist
     *
     * @return void
     */
    protected function onDelete()
    {
    }

    /**
     * Set database connection
     *
     * @param DbConnection $connection Database connection
     *
     * @return self
     */
    public function setConnection(DbConnection $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        return $this;
    }

    /**
     * Getter
     *
     * @param string $property Property
     *
     * @throws IE
     *
     * @return mixed
     */
    public function get($property)
    {
        $func = 'get' . ucfirst($property);
        if (substr($func, -2) === '[]') {
            $func = substr($func, 0, -2);
        }
        // TODO: id check avoids collision with getId. Rethink this.
        if ($property !== 'id' && method_exists($this, $func)) {
            return $this->$func();
        }

        if (! array_key_exists($property, $this->properties)) {
            throw new IE('Trying to get invalid property "%s"', $property);
        }
        return $this->properties[$property];
    }

    public function hasProperty($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return true;
        }
        $func = 'get' . ucfirst($key);
        if (substr($func, -2) === '[]') {
            $func = substr($func, 0, -2);
        }
        if (method_exists($this, $func)) {
            return true;
        }
        return false;
    }

    /**
     * Generic setter
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws IE
     *
     * @return self
     */
    public function set($key, $value)
    {
        $key = (string) $key;
        if ($value === '') {
            $value = null;
        }

        $func = 'validate' . ucfirst($key);
        if (method_exists($this, $func) && $this->$func($value) !== true) {
            throw new IE('Got invalid value "%s" for "%s"', $value, $key);
        }
        $func = 'munge' . ucfirst($key);
        if (method_exists($this, $func)) {
            $value = $this->$func($value);
        }

        $func = 'set' . ucfirst($key);
        if (substr($func, -2) === '[]') {
            $func = substr($func, 0, -2);
        }

        if (method_exists($this, $func)) {
            return $this->$func($value);
        }

        if (! $this->hasProperty($key)) {
            throw new IE('Trying to set invalid key %s', $key);
        }

        if ((is_numeric($value) || is_string($value))
            && (string) $value === (string) $this->get($key)
        ) {
            return $this;
        }

        if ($key === $this->getAutoincKeyName()  && $this->hasBeenLoadedFromDb()) {
            throw new IE('Changing autoincremental key is not allowed');
        }

        return $this->reallySet($key, $value);
    }

    protected function reallySet($key, $value)
    {
        if ($value === $this->properties[$key]) {
            return $this;
        }

        $this->hasBeenModified = true;
        $this->modifiedProperties[$key] = true;
        $this->properties[$key] = $value;
        return $this;
    }

    /**
     * Magic getter
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Magic setter
     *
     * @param  string  $key  Key
     * @param  mixed   $val  Value
     *
     * @return void
     */
    public function __set($key, $val)
    {
        $this->set($key, $val);
    }

    /**
     * Magic isset check
     *
     * @param  string $key
     * @return boolean
     */
    public function __isset($key)
    {
        return array_key_exists($key, $this->properties);
    }

    /**
     * Magic unsetter
     *
     * @param string $key
     * @throws IE
     * @return void
     */
    public function __unset($key)
    {
        if (! array_key_exists($key, $this->properties)) {
            throw new IE('Trying to unset invalid key');
        }
        $this->properties[$key] = $this->defaultProperties[$key];
    }

    /**
     * Runs set() for every key/value pair of the given Array
     *
     * @param  array $props  Array of properties
     * @throws IE
     * @return self
     */
    public function setProperties($props)
    {
        if (! is_array($props)) {
            throw new IE('Array required, got %s', gettype($props));
        }
        foreach ($props as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * Return an array with all object properties
     *
     * @return array
     */
    public function getProperties()
    {
        //return $this->properties;
        $res = array();
        foreach ($this->listProperties() as $key) {
            $res[$key] = $this->get($key);
        }

        return $res;
    }

    protected function getPropertiesForDb()
    {
        return $this->properties;
    }

    public function listProperties()
    {
        return array_keys($this->properties);
    }

    /**
     * Return all properties that changed since object creation
     *
     * @return array
     */
    public function getModifiedProperties()
    {
        $props = array();
        foreach (array_keys($this->modifiedProperties) as $key) {
            if ($key === $this->autoincKeyName) {
                continue;
            }

            $props[$key] = $this->properties[$key];
        }
        return $props;
    }

    /**
     * Whether this object has been modified
     *
     * @return bool
     */
    public function hasBeenModified()
    {
        return $this->hasBeenModified;
    }

    /**
     * Whether the given property has been modified
     *
     * @param  string   $key Property name
     * @return boolean
     */
    protected function hasModifiedProperty($key)
    {
        return array_key_exists($key, $this->modifiedProperties);
    }

    /**
     * Unique key name
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->keyName;
    }

    /**
     * Autoinc key name
     *
     * @return string
     */
    public function getAutoincKeyName()
    {
        return $this->autoincKeyName;
    }

    public function getKeyParams()
    {
        $params = array();
        $key = $this->getKeyName();
        if (is_array($key)) {
            foreach ($key as $k) {
                $params[$k] = $this->get($k);
            }
        } else {
            $params[$key] = $this->get($this->keyName);
        }

        return $params;
    }

    /**
     * Return the unique identifier
     *
     * // TODO: may conflict with ->id
     *
     * @return string
     */
    public function getId()
    {
        // TODO: Doesn't work for array() / multicol key
        if (is_array($this->keyName)) {
            $id = array();
            foreach ($this->keyName as $key) {
                if (! isset($this->properties[$key])) {
                    return null; // Really?
                }
                $id[$key] = $this->properties[$key];
            }
            return $id;
        } else {
            if (isset($this->properties[$this->keyName])) {
                return $this->properties[$this->keyName];
            }
        }
        return null;
    }

    /**
     * Get the autoinc value if set
     *
     * @return string
     */
    public function getAutoincId()
    {
        if (isset($this->properties[$this->autoincKeyName])) {
            return $this->properties[$this->autoincKeyName];
        }
        return null;
    }

    protected function forgetAutoincId()
    {
        if (isset($this->properties[$this->autoincKeyName])) {
            $this->properties[$this->autoincKeyName] = null;
        }

        return $this;
    }

    /**
     * Liefert das benutzte Datenbank-Handle
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function getDb()
    {
        return $this->db;
    }

    public function hasConnection()
    {
        return $this->connection !== null;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Lädt einen Datensatz aus der Datenbank und setzt die entsprechenden
     * Eigenschaften dieses Objekts
     *
     * @throws NotFoundError
     * @return self
     */
    protected function loadFromDb()
    {
        $select = $this->db->select()->from($this->table)->where($this->createWhere());
        $properties = $this->db->fetchRow($select);

        if (empty($properties)) {

            if (is_array($this->getKeyName())) {
                throw new NotFoundError(
                    'Failed to load %s for %s',
                    $this->table,
                    $this->createWhere()
                );
            } else {
                throw new NotFoundError(
                    'Failed to load %s "%s"',
                    $this->table,
                    $this->getLogId()
                );
            }
        }

        return $this->setDbProperties($properties);
    }

    protected function setDbProperties($properties)
    {
        foreach ($properties as $key => $val) {
            if (! array_key_exists($key, $this->properties)) {
                throw new IE(
                    'Trying to set invalid %s key "%s". DB schema change?',
                    $this->table,
                    $key
                );
            }
            if ($val === null) {
                $this->properties[$key] = null;
            } elseif (is_resource($val)) {
                $this->properties[$key] = stream_get_contents($val);
            } else {
                $this->properties[$key] = (string) $val;
            }
        }

        $this->loadedFromDb = true;
        $this->loadedProperties = $this->properties;
        $this->hasBeenModified = false;
        $this->modifiedProperties = array();
        $this->onLoadFromDb();
        return $this;
    }

    public function getOriginalProperties()
    {
        return $this->loadedProperties;
    }

    public function hasBeenLoadedFromDb()
    {
        return $this->loadedFromDb;
    }

    /**
     * Ändert den entsprechenden Datensatz in der Datenbank
     *
     * @return int  Anzahl der geänderten Zeilen
     */
    protected function updateDb()
    {
        $properties = $this->getModifiedProperties();
        if (empty($properties)) {
            // Fake true, we might have manually set this to "modified"
            return true;
        }

        // TODO: Remember changed data for audit and log
        return $this->db->update(
            $this->table,
            $properties,
            $this->createWhere()
        );

    }

    /**
     * Fügt der Datenbank-Tabelle einen entsprechenden Datensatz hinzu
     *
     * @return int  Anzahl der betroffenen Zeilen
     */
    protected function insertIntoDb()
    {
        $properties = $this->getPropertiesForDb();
        if ($this->autoincKeyName !== null) {
            unset($properties[$this->autoincKeyName]);
        }
        // TODO: Remove this!
        if ($this->connection->isPgsql()) {
            foreach ($properties as $key => $value) {
                if (preg_match('/checksum$/', $key)) {
                    $properties[$key] = Util::pgBinEscape($value);
                }
            }
        }

        return $this->db->insert($this->table, $properties);
    }

    /**
     * Store object to database
     *
     * @param  DbConnection $db
     * @return bool Whether storing succeeded
     * @throws IE
     */
    public function store(DbConnection $db = null)
    {
        if ($db !== null) {
            $this->setConnection($db);
        }

        if ($this->validate() !== true) {
            throw new IE('%s[%s] validation failed', $this->table, $this->getLogId());
        }

        if ($this->hasBeenLoadedFromDb() && ! $this->hasBeenModified()) {
            return true;
        }

        $this->beforeStore();
        $table = $this->table;
        $id = $this->getId();
        $result = false;

        try {
            if ($this->hasBeenLoadedFromDb()) {
                if ($this->updateDb() !== false) {
                    $result = true;
                    $this->onUpdate();
                } else {
                    throw new IE(
                        'FAILED storing %s "%s"',
                        $table,
                        $this->getLogId()
                    );
                }
            } else {
                if ($id && $this->existsInDb()) {
                    throw new DuplicateKeyException(
                        'Trying to recreate %s (%s)',
                        $table,
                        $this->getLogId()
                    );
                }

                if ($this->insertIntoDb()) {
                    if ($this->autoincKeyName) {
                        if ($this->connection->isPgsql()) {
                            $this->properties[$this->autoincKeyName] = $this->db->lastInsertId(
                                $table,
                                $this->autoincKeyName
                            );
                        } else {
                            $this->properties[$this->autoincKeyName] = $this->db->lastInsertId();
                        }
                    }
                    // $this->log(sprintf('New %s "%s" has been stored', $table, $id));
                    $this->onInsert();
                    $result = true;
                } else {
                    throw new IE(
                        'FAILED to store new %s "%s"',
                        $table,
                        $this->getLogId()
                    );
                }
            }

        } catch (Exception $e) {
            if ($e instanceof IE) {
                throw $e;
            }

            throw new IE(
                'Storing %s[%s] failed: %s {%s}',
                $this->table,
                $this->getLogId(),
                $e->getMessage(),
                var_export($this->getProperties(), 1) // TODO: Remove properties
            );
        }

        $this->modifiedProperties = array();
        $this->hasBeenModified = false;
        $this->loadedProperties = $this->properties;
        $this->onStore();
        $this->loadedFromDb = true;
        return $result;
    }

    /**
     * Delete item from DB
     *
     * @return int  Affected rows
     */
    protected function deleteFromDb()
    {
        return $this->db->delete(
            $this->table,
            $this->createWhere()
        );
    }

    /**
     * @param string $key
     * @return self
     * @throws IE
     */
    protected function setKey($key)
    {
        $keyname = $this->getKeyName();
        if (is_array($keyname)) {
            if (! is_array($key)) {
                throw new IE(
                    '%s has a multicolumn key, array required',
                    $this->table
                );
            }
            foreach ($keyname as $k) {
                if (! array_key_exists($k, $key)) {
                    // We allow for null in multicolumn keys:
                    $key[$k] = null;
                }
                $this->set($k, $key[$k]);
            }
        } else {
            $this->set($keyname, $key);
        }
        return $this;
    }

    protected function existsInDb()
    {
        $result = $this->db->fetchRow(
            $this->db->select()->from($this->table)->where($this->createWhere())
        );
        return $result !== false;
    }

    protected function createWhere()
    {
        if ($id = $this->getAutoincId()) {
            return $this->db->quoteInto(
                sprintf('%s = ?', $this->autoincKeyName),
                $id
            );
        }

        $key = $this->getKeyName();

        if (is_array($key) && ! empty($key)) {
            $where = array();
            foreach ($key as $k) {
                if ($this->hasBeenLoadedFromDb()) {
                    if ($this->loadedProperties[$k] === null) {
                        $where[] = sprintf('%s IS NULL', $k);
                    } else {
                        $where[] = $this->db->quoteInto(
                            sprintf('%s = ?', $k),
                            $this->loadedProperties[$k]
                        );
                    }
                } else {
                    if ($this->properties[$k] === null) {
                        $where[] = sprintf('%s IS NULL', $k);
                    } else {
                        $where[] = $this->db->quoteInto(
                            sprintf('%s = ?', $k),
                            $this->properties[$k]
                        );
                    }
                }
            }

            return implode(' AND ', $where);
        } else {
            if ($this->hasBeenLoadedFromDb()) {
                return $this->db->quoteInto(
                    sprintf('%s = ?', $key),
                    $this->loadedProperties[$key]
                );
            } else {
                return $this->db->quoteInto(
                    sprintf('%s = ?', $key),
                    $this->properties[$key]
                );
            }
        }
    }

    protected function getLogId()
    {
        $id = $this->getId();
        if (is_array($id)) {
            $logId = json_encode($id);
        } else {
            $logId = $id;
        }

        return $logId;
    }

    public function delete()
    {
        $table = $this->table;

        if (! $this->hasBeenLoadedFromDb()) {
            throw new IE(
                'Cannot delete %s "%s", it has not been loaded from Db',
                $table,
                $this->getLogId()
            );
        }

        if (! $this->existsInDb()) {
            throw new IE(
                'Cannot delete %s "%s", it does not exist',
                $table,
                $this->getLogId()
            );
        }
        $this->beforeDelete();
        if (! $this->deleteFromDb()) {
            throw new IE(
                'Deleting %s (%s) FAILED',
                $table,
                $this->getLogId()
            );
        }
        // $this->log(sprintf('%s "%s" has been DELETED', $table, this->getLogId()));
        $this->onDelete();
        $this->loadedFromDb = false;
        return true;
    }

    public function __clone()
    {
        $this->onClone();
        $this->forgetAutoincId();
        $this->loadedFromDb    = false;
        $this->hasBeenModified = true;
    }

    protected function onClone()
    {
    }

    /**
     * @param array $properties
     * @param DbConnection|null $connection
     *
     * @return static
     */
    public static function create($properties = array(), DbConnection $connection = null)
    {
        $obj = new static();
        if ($connection !== null) {
            $obj->setConnection($connection);
        }
        $obj->setProperties($properties);
        return $obj;
    }

    protected static function classWasPrefetched()
    {
        $class = get_called_class();
        return array_key_exists($class, self::$prefetched);
    }

    protected static function getPrefetched($key)
    {
        $class = get_called_class();
        if (static::hasPrefetched($key)) {
            if (is_string($key)
                && array_key_exists($class, self::$prefetchedNames)
                && array_key_exists($key, self::$prefetchedNames[$class])
            ) {
                return self::$prefetched[$class][
                    self::$prefetchedNames[$class][$key]
                ];
            } else {
                return self::$prefetched[$class][$key];
            }
        } else {
            return false;
        }
    }

    protected static function hasPrefetched($key)
    {
        $class = get_called_class();
        if (! array_key_exists($class, self::$prefetchStats)) {
            self::$prefetchStats[$class] = (object) array(
                'miss'         => 0,
                'hits'         => 0,
                'hitNames'     => 0,
                'combinedMiss' => 0
            );
        }

        if (is_array($key)) {
            self::$prefetchStats[$class]->combinedMiss++;
            return false;
        }

        if (array_key_exists($class, self::$prefetched)) {
            if (is_string($key)
                && array_key_exists($class, self::$prefetchedNames)
                && array_key_exists($key, self::$prefetchedNames[$class])
            ) {
                self::$prefetchStats[$class]->hitNames++;
                return true;
            } elseif (array_key_exists($key, self::$prefetched[$class])) {
                self::$prefetchStats[$class]->hits++;
                return true;
            } else {
                self::$prefetchStats[$class]->miss++;
                return false;
            }

        } else {
            self::$prefetchStats[$class]->miss++;
            return false;
        }
    }

    public static function getPrefetchStats()
    {
        return self::$prefetchStats;
    }

    public static function loadWithAutoIncId($id, DbConnection $connection)
    {
        if ($prefetched = static::getPrefetched($id)) {
            return $prefetched;
        }

        /** @var DbObject $obj */
        $obj = new static;
        $obj->setConnection($connection)
            ->set($obj->autoincKeyName, $id)
            ->loadFromDb();
        return $obj;
    }

    public static function load($id, DbConnection $connection)
    {
        if ($prefetched = static::getPrefetched($id)) {
            return $prefetched;
        }

        /** @var DbObject $obj */
        $obj = new static;
        $obj->setConnection($connection)->setKey($id)->loadFromDb();
        return $obj;
    }

    /**
     * @param DbConnection $connection
     * @param \Zend_Db_Select $query
     * @param string|null $keyColumn
     *
     * @return self[]
     */
    public static function loadAll(DbConnection $connection, $query = null, $keyColumn = null)
    {
        $objects = array();
        $db = $connection->getDbAdapter();

        if ($query === null) {
            $dummy = new static;
            $select = $db->select()->from($dummy->table);
        } else {
            $select = $query;
        }

        $rows = $db->fetchAll($select);

        foreach ($rows as $row) {
            /** @var DbObject $obj */
            $obj = new static;
            $obj->setConnection($connection)->setDbProperties($row);
            if ($keyColumn === null) {
                $objects[] = $obj;
            } else {
                $objects[$row->$keyColumn] = $obj;
            }
        }

        return $objects;
    }

    /**
     * @param DbConnection $connection
     * @param bool $force
     *
     * @return static[]
     */
    public static function prefetchAll(DbConnection $connection, $force = false)
    {
        $dummy = static::create();
        $class = get_class($dummy);
        $autoInc = $dummy->getAutoincKeyName();
        $keyName = $dummy->getKeyName();

        if ($force || ! array_key_exists($class, self::$prefetched)) {
            self::$prefetched[$class] = static::loadAll($connection, null, $autoInc);
            if (! is_array($keyName) && $keyName !== $autoInc) {
                foreach (self::$prefetched[$class] as $k => $v) {
                    self::$prefetchedNames[$class][$v->$keyName] = $k;
                }
            }
        }

        return self::$prefetched[$class];
    }

    public static function clearPrefetchCache()
    {
        $class = get_called_class();
        if (! array_key_exists($class, self::$prefetched)) {
            return;
        }

        unset(self::$prefetched[$class]);
        unset(self::$prefetchedNames[$class]);
        unset(self::$prefetchStats[$class]);
    }

    public static function clearAllPrefetchCaches()
    {
        self::$prefetched = array();
        self::$prefetchedNames = array();
        self::$prefetchStats = array();
    }

    /**
     * @param $id
     * @param DbConnection $connection
     * @return bool
     */
    public static function exists($id, DbConnection $connection)
    {
        if (static::getPrefetched($id)) {
            return true;
        } elseif (static::classWasPrefetched()) {
            return false;
        }

        /** @var DbObject $obj */
        $obj = new static;
        $obj->setConnection($connection)->setKey($id);
        return $obj->existsInDb();
    }

    public function __destruct()
    {
        unset($this->db);
        unset($this->connection);
    }
}