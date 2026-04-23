<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
/*           dd(app()->getLocale());
 */
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user,
            ],

            // 🔥 WICHTIG: Fallback-Logik einbauen
            'locale' => $user?->language
                ?? session('locale')
                ?? app()->getLocale(),
        ]);
    }
}
