<?php

namespace Northrook\ArrayAccessor;

/**
 * @used-by \Northrook\ArrayAccessor
 */
trait ArrayConstructor {

    /**
     * Create a new DelineatedArray
     *
     * @param mixed|array       $array
     * @param bool              $parse
     * @param non-empty-string  $delimiter  [.] The character to use as a delimiter.
     *
     * @return void
     */
    public function __construct(
            mixed                     $array = [],
            bool                      $parse = false,
            protected readonly string $delimiter = ".",
    ) {
        // Sanity check for delimiter value
        if ( !$this->delimiter ) {
            throw new \ValueError( static::class . ' $delimiter cannot be empty.' );
        }

        // Initialize the array values
        $this->arrayValue( $array, $parse );
    }
}