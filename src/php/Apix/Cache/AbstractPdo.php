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
 * PDO cache wrapper with tag support.
 *
 * @author Franck Cassedanne <franck at ouarz.net>
 */
abstract class AbstractPdo extends AbstractCache
{

    /**
     * Constructor.
     *
     * @param \PDO  $pdo     An instance of a PDO class.
     * @param array $options Array of options.
     */
    public function __construct(\PDO $pdo, array $options=null)
    {
        // default options
        $this->options['db_table']   = 'cache';
        $this->options['serializer'] = 'php'; // none, php, igBinary, json.

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        parent::__construct($pdo, $options);
        $this->setSerializer($this->options['serializer']);

        // Initialises the database.
        $this->adapter->exec( $this->getSql('init') );
    }

    /**
     * Creates the database indexes
     *
     * @return self Provides a fluent interface
     */
    public function createIndexes()
    {
        $this->createIndexe('key_idx');
        $this->createIndexe('exp_idx');
        $this->createIndexe('tag_idx');

        return $this;
    }

    private function createIndexe($index)
    {
        if (isset($this->options[$index]) && $this->options[$index]) {
            return $this->adapter->exec($this->getSql($index));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function loadKey($key)
    {
        $sql = $this->getSql('loadKey');
        $values = array('key' => $this->mapKey($key), 'now' => time());

        $cached = $this->exec($sql, $values)->fetch();

        if (null !== $cached['data'] && null !== $this->serializer) {
            return $this->serializer->unserialize($cached['data']);
        }

        return false === $cached ? null : $cached['data'];
    }

    /**
     * {@inheritdoc}
     */
    public function loadTag($tag)
    {
        $sql = $this->getSql('loadTag');
        // $tag = $this->mapTag($tag);
        $values = array('tag' => "%$tag%", 'now' => time());

        $items = $this->exec($sql, $values)->fetchAll();

        $keys = array();
        foreach ($items as $item) {
            $keys[] = $item['key'];
        }

        return empty($keys) ? null : $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function save($data, $key, array $tags=null, $ttl=null)
    {
        $values = array(
            'key'   => $this->mapKey($key),
            'data'  => null !== $this->serializer
                        ? $this->serializer->serialize($data)
                        : $data,
            'exp'   => null !== $ttl && 0 !== $ttl ? time()+$ttl : null,
            'dated' => $this->timestamp()
        );

        $values['tags'] = $this->options['tag_enable'] && null !== $tags
                            ? implode(', ', $tags)
                            : null;

        // upsert
        $sql = $this->getSql('update');
        $nb = $this->exec($sql, $values)->rowCount();
        if ($nb == 0) {
            $sql = $this->getSql('insert');
            $nb = $this->exec($sql, $values)->rowCount();
        }

        return (boolean) $nb;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $sql = $this->getSql('delete');
        $values = array($this->mapKey($key));

        return (boolean) $this->exec($sql, $values)->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function clean(array $tags)
    {
        $values = array();
        foreach ($tags as $tag) {
            // $tag = $this->mapTag($tag);
            $values[] = '%' . $tag . '%';
        }

        $sql = $this->getSql(
            'clean', implode(' OR ', array_fill(
                    0, count($tags), $this->getSql('clean_like')))
        );

        return (boolean) $this->exec($sql, $values)->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function flush($all=false)
    {
        if (true === $all) {
            return false !== $this->adapter->exec($this->getSql('flush_all'));
        }

        return (boolean) $this->adapter->exec($this->getSql('flush'));
    }

    /**
     * Purges expired items.
     *
     * @param  integer|null $add Extra time in second to add.
     * @return boolean      Returns True on success or False on failure.
     */
    public function purge($add=null)
    {
        $time = null == $add ? time() : time()+$add;

        return (boolean) $this->adapter->exec($this->getSql('purge', $time));
    }

    /**
     * Gets the named SQL definition.
     *
     * @param  string         $key
     * @param  string|integer $value An additional value.
     * @return string
     */
    protected function getSql($key, $value=null)
    {
        return sprintf(
            $this->sql_definitions[$key],
            $this->options['db_table'],
            $value
        );
    }

    /**
     * Prepares and executes a SQL query.
     *
     * @param  string        $sql    The SQL to prepare.
     * @param  array         $values The values to execute.
     * @return \PDOStatement Provides a fluent interface
     */
    protected function exec($sql, array $values)
    {
        $prep = $this->adapter->prepare($sql);
        $prep->execute($values);

        return $prep;
    }

}
