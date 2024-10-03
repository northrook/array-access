<?php

namespace Northrook\ArrayAccessor;

use Northrook\ArrayAccessor;

/**
 * @template TKey of array-key
 * @template TValue of mixed
 *
 * @extends ArrayAccessor<TKey,TValue>
 */
trait ArrayConstructor
{
    /**
     * Create a new DelineatedArray.
     *
     * @param array|\Northrook\ArrayAccessor<TKey, TValue>|string $array
     * @param bool                                                $parse
     *
     * @return void
     */
    public function __construct(
        array|ArrayAccessor|string $array = [],
        bool                       $parse = false,
    ) {
        // Initialize the array values
        $this->arrayValue( $array, $parse );
    }
}
