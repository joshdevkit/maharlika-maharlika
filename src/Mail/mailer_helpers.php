<?php


if (!function_exists('mailer')) {
    function mailer()
    {
        static $mailer = null;

        if ($mailer === null) {
            $config = config('mail');
            $mailer = new \Maharlika\Mail\Mailer($config);
        }

        return $mailer;
    }
}
