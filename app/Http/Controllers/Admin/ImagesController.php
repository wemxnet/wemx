<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class ImagesController extends Controller
{
    public function index()
    {
        return view('admin::settings.images');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:6144', // max 6MB
            'file_name' => 'nullable|string|max:255',
        ]);

        // format file name using Str::slug if file_name is provided
        if ($request->file_name) {
            $request->merge(['file_name' => Str::slug($request->file_name)]);
        }

        // if file name already contains an extension, remove it
        if ($request->file_name && Str::contains($request->file_name, '.'))
        {
            $request->merge(['file_name' => pathinfo($request->file_name, PATHINFO_FILENAME)]);
        }

        // store the image in the root/public/assets/common/img/ directory
        $fileName = $request->file_name ? $request->file_name . '.' . $request->image->getClientOriginalExtension() : $request->image->getClientOriginalName();
        $request->image->move(public_path('assets/common/img/'), $fileName);

        return redirect()->back();
    }

    public function destroy($file_name)
    {
        $filePath = public_path('assets/common/img/' . $file_name);

        if (file_exists($filePath)) {
            unlink($filePath);
            return redirect()->back()->with('success', 'Image deleted successfully.');
        }

        return redirect()->back()->with('error', 'Image not found.');
    }
}
