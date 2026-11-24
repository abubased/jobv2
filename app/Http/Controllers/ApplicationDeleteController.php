<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ApplicationDeleteController extends Controller
{
    public function confirm(Application $application): View
    {
        return view('applications.delete-confirm', ['application' => $application]);
    }

    public function destroy(Application $application): RedirectResponse
    {
        // এখানে প্রয়োজনে পারমিশন চেক যোগ করুন, যেমন:
        // abort_unless(auth()->user()->can('delete', $application), 403);

        $application->delete();

        return redirect('/all-applications')->with('success', 'আবেদনটি মুছে ফেলা হয়েছে।');
    }
}
