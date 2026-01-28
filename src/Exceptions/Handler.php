<?php

namespace Maharlika\Exceptions;

use Maharlika\Validation\ValidationException;
use Maharlika\Http\RedirectResponse;

class Handler
{
    public function render($request, \Throwable $e)
    {
        if ($e instanceof ValidationException) {
            return $this->handleValidationException($request, $e);
        }
        
        \Spatie\Ignition\Ignition::make()->register();
    }

    protected function handleValidationException($request, ValidationException $e)
    {
        $validator = $e->validator();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        return RedirectResponse::back()
            ->withInput($request->all())
            ->withErrors($validator->errors());
    }
}