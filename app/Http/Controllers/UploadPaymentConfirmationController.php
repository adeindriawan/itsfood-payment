<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UploadPaymentConfirmationController extends Controller
{
    public function upload(Request $request)
    {
        $response = cloudinary()->upload($request->file('file')->getRealPath())->getSecurePath();

        return response()->json($response);
    }
}
