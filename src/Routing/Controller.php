<?php

namespace Maharlika\Routing;

use Maharlika\Routing\Traits\HasMiddleware;
use Maharlika\Routing\Traits\RegeneratesSession;
use Maharlika\Support\Traits\Macroable;

abstract class Controller
{
    use Macroable, RegeneratesSession, HasMiddleware;
}
