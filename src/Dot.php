<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\ArrayAccessor\{ArrayConstructor, PublicSetArrayValue};

/**
 * @template TKey of array-key
 * @template TValue as mixed|array<TKey,TValue>
 *
 * @extends ArrayAccessor<TKey,TValue>
 */
class Dot extends ArrayAccessor
{
    use ArrayConstructor, PublicSetArrayValue;
}
