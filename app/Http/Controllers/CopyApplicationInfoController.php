<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\View\View;

class CopyApplicationInfoController extends Controller
{
    public function show(Application $application): View
    {
        $text = "নাম- {$application->name}\nযোগাযোগের নাম্বার- {$application->phone}\nঠিকানা- {$application->address}";

        return view('applications.copy', ['text' => $text]);
    }
}
