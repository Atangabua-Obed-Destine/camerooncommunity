<?php

namespace App\Http\Controllers;

use App\Models\ComingSoonSignup;
use App\Models\User;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $memberCount = User::count();
        $regionCount = User::whereNotNull('current_region')
            ->distinct('current_region')
            ->count('current_region');

        return view('home', [
            'memberCount' => $memberCount,
            'regionCount' => max($regionCount, 1),
        ]);
    }
}
