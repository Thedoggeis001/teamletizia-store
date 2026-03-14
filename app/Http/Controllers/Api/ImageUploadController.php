<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120']
        ]);

        $file = $request->file('image');

        $path = $file->store('products', 'public');

        return response()->json([
            'success' => true,
            'url' => asset('storage/' . $path)
        ]);
    }
}