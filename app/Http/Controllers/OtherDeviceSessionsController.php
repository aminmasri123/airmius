<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Jetstream\Http\Controllers\Inertia\OtherBrowserSessionsController;

class OtherDeviceSessionsController extends OtherBrowserSessionsController
{
    protected function deleteOtherSessionRecords(Request $request)
    {
        parent::deleteOtherSessionRecords($request);
        // Browser logout of other devices also revokes mobile device tokens.
        $request->user()->tokens()->delete();
    }
}
