<?php

namespace Northrook\ArrayAccessor;

use Northrook\ArrayAccessor;

/**
 * @template TKey of array-key
 * @template TValue of mixed
 *
 * @extends ArrayAccessor<TKey,TValue>
 */
trait PublicSetArrayValue
{
    /**
     * @param array|\Northrook\ArrayAccessor<TKey, TValue>|string $array
     * @param bool                                                $parse
     *
     * @return ArrayAccessor
     */
    public function setArrayValue(
        array|ArrayAccessor|string $array,
        bool                       $parse = false,
    ) : ArrayAccessor {
        return $this->arrayValue( $array, $parse );
    }
}
