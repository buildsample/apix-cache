<?php

/**
 *
 * This file is part of the Apix Project.
 *
 * (c) Franck Cassedanne <franck at ouarz.net>
 *
 * @license     http://opensource.org/licenses/BSD-3-Clause  New BSD License
 *
 */

namespace Apix\Cache;

/**
 * Base class provides the cache wrappers structure.
 *
 * @author Franck Cassedanne <franck at ouarz.net>
 */
abstract class AbstractCache implements Adapter
{

    /**
     * Holds an injected adapter.
     * @var object
     */
    protected $adapter = null;

    /**
     * @var Serializer\Adapter
     */
    protected $serializer;

    /**
     * Holds some generic default options.
     * @var array
     */
    protected $options = array(
        'prefix_key'        => 'apix-cache-key:', // prefix cache keys
        'prefix_tag'        => 'apix-cache-tag:', // prefix cache tags
        'tag_enable'        => true,              // wether to enable tagging
        'format_timestamp'  => 'Y-m-d H:i:s'      // the format of timestamps
    );

    /**
     * Constructor use to set the adapter and dedicated options.
     *
     * @param object|null $adapter The adapter to set, generally an object.
     * @param array|null  $options The array of user options.
     */
    public function __construct($adapter=null, array $options=null)
    {
        $this->adapter = $adapter;
        $this->setOptions($options);
    }

    /**
     * Sets and merges the options (overriding the default options).
     *
     * @param array|null $options The array of user options.
     */
    public function setOptions(array $options=null)
    {
        if (null !== $options) {
            $this->options = $options+$this->options;
        }
    }

    /**
     * Sets and merges the options (overriding the default options).
     *
     * @param array|null $options The array of user options.
     */
    public function getOption($key)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
    }

    /**
     * Returns a prefixed and sanitased cache id.
     *
     * @param  string $key The base key to prefix.
     * @return string
     */
    public function mapKey($key)
    {
        return $this->sanitise($this->options['prefix_key'] . $key);
    }

    /**
     * Returns a prefixed and sanitased cache tag.
     *
     * @param  string $tag The base tag to prefix.
     * @return string
     */
    public function mapTag($tag)
    {
        return $this->sanitise($this->options['prefix_tag'] . $tag);
    }

    /**
     * Returns a sanitased string for keying/tagging purpose.
     *
     * @param  string $key The string to sanitise.
     * @return string
     */
    public function sanitise($key)
    {
        return $key;
        // return str_replace(array('/', '\\', ' '), '_', $key);
    }

    /**
     * Gets the injected adapter.
     *
     * @return object
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Sets the serializer.
     *
     * @param string $name
     */
    public function setSerializer($name)
    {
        if (null === $name) {
            $this->serializer = null;
        } else {
            $classname = __NAMESPACE__ . '\Serializer\\';
            $classname .= ucfirst(strtolower($name));
            $this->serializer = new $classname;
        }
    }

    /**
     * Gets the serializer.
     *
     * @return Serializer\Adapter
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * Returns a formated timestamp.
     *
     * @param integer|null $time If null, use the current time.
     */
    public function timestamp($time=null)
    {
        return date(
            $this->options['format_timestamp'],
            null != $time ? $time : time()
        );
    }

    /**
     * Retrieves the cache content for the given key or the keys for a given tag.
     *
     * @param  string     $key  The cache id to retrieve.
     * @param  string     $type The type of the key (either 'key' or 'tag').
     * @return mixed|null Returns the cached data or null if not set.
     */
    public function load($key, $type='key')
    {
        return $type == 'key' ? $this->loadKey($key) : $this->loadTag($key);
    }

}
