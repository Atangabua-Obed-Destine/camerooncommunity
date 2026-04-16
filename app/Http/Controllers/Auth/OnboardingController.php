<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Mark onboarding as complete (fallback POST route).
     */
    public function complete(Request $request)
    {
        $request->user()->update(['onboarded_at' => now()]);

        return redirect()->route('yard');
    }
}
