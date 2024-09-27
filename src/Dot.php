<?php

namespace Northrook;

use Northrook\ArrayAccessor\ArrayConstructor;
use Northrook\ArrayAccessor\PublicSetArrayValue;

class Dot extends ArrayAccessor
{
    use ArrayConstructor, PublicSetArrayValue;
}