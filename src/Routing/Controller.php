<?php

namespace Maharlika\Routing;

use Maharlika\Routing\Traits\HasMiddleware;
use Maharlika\Routing\Traits\RegeneratesSession;

abstract class Controller
{
    use RegeneratesSession, HasMiddleware;
}
