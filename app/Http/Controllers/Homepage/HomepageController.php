<?php

namespace App\Http\Controllers\Homepage;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class HomepageController extends Controller
{
    public function index(Request $request)
    {
        info('index');
        info($request);

        return redirect('/homepage')->with([
            'status' => 'success',
            'message' => 'Updated successfully!',
        ]);
    }

    public function config(Request $request)
    {
        $school = $request->query('school');
        $app = $request->query('app');
        info($school);
        info($app);

        $data = [
            'logo' => config('spa.logo', ''),
            'copyright' => config('spa.copyright', ''),
            'title' => config('spa.title', 'Fresh Laravel'),

        ];

        return response()->json($data, 200);
    }
}
