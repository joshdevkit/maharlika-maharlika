<?php

namespace Maharlika\Auth;

use Maharlika\Auth\Authenticatable;
use Maharlika\Auth\MustVerifyEmail;
use Maharlika\Auth\Passwords\CanResetPassword;
use Maharlika\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Maharlika\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Maharlika\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Maharlika\Database\FluentORM\Model;
use Maharlika\Auth\Access\Authorizable;


class Authentication extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract
{
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail;
}
