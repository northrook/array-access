<?php

namespace Northrook\ArrayAccessor;

use Northrook\ArrayAccessor;


trait PublicSetArrayValue
{
    public function setArrayValue(
        array | ArrayAccessor | string $array,
        bool                           $parse = false,
    ) : ArrayAccessor {
        return $this->arrayValue( $array, $parse );
    }
}