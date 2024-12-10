<?php

declare(strict_types=1);

namespace Northrook;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Traversable;
use ValueError;

/**
 * Dot Properties.
 *
 * Inspired by adbario/php-dot-notation, and Laravel Collection.
 *
 * @template TKey of array-key
 * @template TValue as mixed|array<TKey,TValue>
 *
 * @implements \ArrayAccess<TKey, TValue>       for use with $class['key'] = 'value'
 * @implements \IteratorAggregate<TKey, TValue> for use in iterators like array_map
 */
class ArrayAccessor implements IteratorAggregate, ArrayAccess
{
    protected const int
        GET_ALL       = 0,       // Returns the full array, including the primary value
        PRIMARY_VALUE = 1, // Returns the primary value
        GET_ARRAY     = 2;     // Returns the array, without the primary value

    /** @var non-empty-string */
    protected const string DELIMITER = '.';

    /** @var non-empty-string */
    public const string    PRIMARY_VALUE_KEY = '[=]';

    /** @var array<TKey, TValue> The stored items */
    protected array $array = [];

    // ::: Assign ::::::::::::

    /**
     * Set a given key / value pair or pairs
     * if the key doesn't exist already.
     *
     * @param array<TKey, TValue>|int|string $keys
     * @param TValue                         $value
     *
     * @return $this
     */
    public function add( array|int|string $keys, mixed $value = null ) : self
    {
        if ( \is_array( $keys ) ) {
            foreach ( $keys as $key => $value ) {
                $this->add( $key, $value );
            }
        }
        elseif ( $this->get( $keys ) === null ) {
            $this->set( $keys, $value );
        }

        return $this;
    }

    /**
     * Set a given key / value pair or pairs.
     *
     * @param array<TKey, TValue>|int|string $keys
     * @param TValue                         $value
     *
     * @return $this
     */
    public function set( array|int|string $keys, mixed $value = [] ) : self
    {
        // Allows setting multiple values
        if ( \is_array( $keys ) ) {
            foreach ( $keys as $key => $value ) {
                $this->set( $key, $value );
            }

            return $this;
        }

        $items = &$this->array;

        foreach ( \explode( $this::DELIMITER, (string) $keys ) as $key ) {
            // If there is noting, we create an empty array
            if ( ! isset( $items[$key] ) ) {
                $items[$key] = [];
            }
            // If what is there, is not an array
            elseif ( ! \is_array( $items[$key] ) ) {
                // $items[ $key ] = [];
                $items[$key] = [$this::PRIMARY_VALUE_KEY => $items[$key]];
            }

            $items = &$items[$key];
        }

        $items = $value;

        return $this;
    }

    /**
     * Push a given value to the end of the array in a given key.
     *
     * @param int|string $key
     * @param TValue     $value
     *
     * @return $this
     */
    public function push( int|string $key, mixed $value = null ) : self
    {
        $items = $this->get( $key );

        if ( \is_array( $items ) || null === $items ) {
            $items[] = $value;
            /** @var TValue $items */
            $this->set( $key, $items );
        }

        return $this;
    }

    // ::: Access ::::::::::::

    /**
     * Check if a given key or keys exists.
     *
     * @param array<TKey>|int|string $keys
     *
     * @return bool
     */
    public function has( int|array|string $keys ) : bool
    {
        $keys = (array) $keys;

        if ( ! $this->array || [] === $keys ) {
            return false;
        }

        foreach ( $keys as $key ) {
            $items = $this->array;

            if ( \array_key_exists( $key, $items ) ) {
                continue;
            }

            foreach ( \explode( $this::DELIMITER, $key ) as $segment ) {
                if ( ! \is_array( $items ) || ! \array_key_exists( $segment, $items ) ) {
                    return false;
                }

                $items = $items[$segment];
            }
        }

        return true;
    }

    /**
     * Return the value of a given key.
     *
     * @param int|string $key
     * @param mixed      $default
     *
     * @return null|array<TKey, TValue>|TValue
     */
    public function get( int|string $key, mixed $default = null ) : mixed
    {
        [$key, $get] = $this->propertyKey( $key );

        // Return early if the $key is in the top layer
        if ( \array_key_exists( $key, $this->array ) ) {
            return $this->array[$key];
        }

        // If the $key doesn't have a deliminator at this point, it does not exist
        if ( ! \is_string( $key ) || ! \str_contains( $key, $this::DELIMITER ) ) {
            return $default;
        }

        $items = $this->array;

        foreach ( \explode( $this::DELIMITER, $key ) as $segment ) {
            if ( ! \is_array( $items ) || ! \array_key_exists( $segment, $items ) ) {
                return $default;
            }

            $items = &$items[$segment];
        }

        // If the item isn't an array we can just return it
        if ( ! \is_array( $items ) || $get === $this::GET_ALL ) {
            return $items;
        }

        if ( $get === $this::PRIMARY_VALUE && \array_key_exists( $this::PRIMARY_VALUE_KEY, $items ) ) {
            return $items[$this::PRIMARY_VALUE_KEY];
        }

        $this->unsetPropertyValues( $items );

        return $items;
    }

    /**
     * Return the value of a given key and
     * delete the key.
     *
     * @param null|int|string $key
     * @param mixed           $default
     *
     * @return null|array<TKey, TValue>|TValue
     */
    public function pull( int|string|null $key = null, mixed $default = null ) : mixed
    {
        if ( null === $key ) {
            $value = $this->all();
            $this->clear();

            return $value;
        }

        $value = $this->get( $key, $default );
        $this->delete( $key );

        return $value;
    }

    /**
     * Flatten an array with the given character as a key delimiter.
     *
     * @param string               $delimiter
     * @param ?array<TKey, TValue> $array
     * @param string               $previousKey
     *
     * @return array<TKey, TValue>
     */
    public function flatten( string $delimiter = '.', ?array $array = null, string $previousKey = '' ) : array
    {
        $flatten = [];

        $array ??= $this->array;

        foreach ( $array as $key => $value ) {
            if ( \is_array( $value ) && ! empty( $value ) ) {
                $flatten[] = $this->flatten( $delimiter, $value, $previousKey.$key.$delimiter );
            }
            else {
                // TODO: The trim here could likely be improved
                $key = $key === $this::PRIMARY_VALUE_KEY ? \trim( $previousKey, $delimiter ) : $previousKey.$key;

                $flatten[] = [$key => $value];
            }
        }

        return \array_merge( ...$flatten );
    }

    /**
     * Return all the stored items.
     *
     * @return array<TKey, TValue>
     */
    public function all() : array
    {
        return $this->array;
    }

    // ::: Destructive ::::::::::::

    /**
     * Delete the contents of a given key or keys.
     *
     * @param null|array<TKey>|int|string $keys
     *
     * @return $this
     */
    public function clear( int|array|string $keys = null ) : self
    {
        if ( null === $keys ) {
            $this->array = [];

            return $this;
        }

        foreach ( (array) $keys as $key ) {
            $this->set( $key );
        }

        return $this;
    }

    /**
     * Delete the given key or keys.
     *
     * @param TKey[] ...$keys
     *
     * @return $this
     */
    public function delete( int|string ...$keys ) : self
    {
        foreach ( $keys as $key ) {
            if ( \array_key_exists( $key, $this->array ) ) {
                unset( $this->array[$key] );

                continue;
            }

            $items       = &$this->array;
            $segments    = \explode( $this::DELIMITER, (string) $key );
            $lastSegment = \array_pop( $segments );

            foreach ( $segments as $segment ) {
                if ( ! isset( $items[$segment] ) || ! \is_array( $items[$segment] ) ) {
                    continue 2;
                }

                $items = &$items[$segment];
            }

            unset( $items[$lastSegment] );
        }

        return $this;
    }

    // ::: Array ::::::::::::

    /**
     * @param array<TKey, TValue>|ArrayAccessor<TKey, TValue>|string $array
     * @param bool                                                   $parse
     *
     * @return $this
     */
    final protected function arrayValue(
        array|ArrayAccessor|string $array,
        bool                       $parse = false,
    ) : self {
        $array = $this->arrayItems( $array );
        if ( $parse ) {
            return $this->set( $array );
        }
        $this->array = $array;
        return $this;
    }

    /**
     * Return the given items as an array.
     *
     * @param array<TKey, TValue>|self<TKey, TValue>|string $items
     *
     * @return array<TKey,mixed|TValue>
     */
    final protected function arrayItems( array|ArrayAccessor|string $items ) : array
    {
        return match ( true ) {
            \is_array( $items )      => $items,
            $items instanceof static => $items->all(),
            default                  => (array) $items,
        };
    }

    // ::: Internal Utility ::::::::::::

    /**
     * @param int|string $key
     *
     * @return array<array-key, array-key>
     */
    final protected function propertyKey( int|string $key ) : array
    {
        if ( '' === $key ) {
            throw new ValueError( $this::class.' property $key cannot be empty.' );
        }

        return [
            \trim( (string) $key, '.:' ),
            match ( \substr( (string) $key, -1 ) ) {
                '.'     => $this::GET_ARRAY,          // Returns the array, without the property value
                ':'     => $this::GET_ALL,            // Returns the full array, including the property value
                default => $this::PRIMARY_VALUE,      // Returns the property value
            },
        ];
    }

    /**
     * @param array<array-key, mixed> $items
     *
     * @return void
     */
    final protected function unsetPropertyValues( array &$items ) : void
    {
        foreach ( $items as $key => $value ) {
            if ( $key === $this::PRIMARY_VALUE_KEY ) {
                unset( $items[$key] );
            }

            if ( \is_array( $value ) ) {
                $this->unsetPropertyValues( $items[$key] );
            }
        }
    }

    // ::: ArrayIterator :::::::::::::::::

    /**
     * Get an iterator for the stored items.
     *
     * @return ArrayIterator<TKey, TValue>
     */
    final public function getIterator() : Traversable
    {
        return new ArrayIterator( $this->array );
    }

    // ::: ArrayAccess :::::::::::::::::

    final public function offsetExists( mixed $offset ) : bool
    {
        return $this->has( $offset );
    }

    /**
     * @param TKey $offset
     *
     * @return null|array<TKey, TValue>|TValue
     */
    final public function offsetGet( mixed $offset ) : mixed
    {
        return $this->get( $offset );
    }

    final public function offsetSet( mixed $offset, mixed $value ) : void
    {
        if ( ! $offset ) {
            $this->array[] = $value;
        }
        else {
            $this->set( $offset, $value );
        }
    }

    final public function offsetUnset( mixed $offset ) : void
    {
        $this->delete( $offset );
    }
}
