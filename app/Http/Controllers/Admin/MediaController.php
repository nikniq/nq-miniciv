<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Media;

class MediaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $media = Media::orderBy('created_at', 'desc')->paginate(30);
        return view('admin.media.index', compact('media'));
    }

    public function create()
    {
        return view('admin.media.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,svg,webp', 'max:8192'],
        ]);

        $file = $request->file('file');
        $disk = 'public';
        $now = now();
        $path = 'wp-uploads/'.$now->format('Y').'/'.$now->format('m');

        try {
            $stored = $file->store($path, $disk);

            $media = Media::create([
                'disk' => $disk,
                'path' => $stored,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            return redirect()->route('admin.media.index')->with('success', 'Uploaded.');
        } catch (\Exception $e) {
            Log::error('Media upload failed', [
                'original_name' => $file ? $file->getClientOriginalName() : null,
                'mime' => $file ? $file->getClientMimeType() : null,
                'size' => $file ? $file->getSize() : null,
                'disk' => $disk,
                'path' => $path,
                'exception' => $e->getMessage(),
            ]);

            return back()->withErrors(['file' => 'Upload failed: '.$e->getMessage()])->withInput();
        }
    }

    public function destroy(Media $media)
    {
        try {
            if ($media->disk && Storage::disk($media->disk)->exists($media->path)) {
                Storage::disk($media->disk)->delete($media->path);
            }
        } catch (\Exception $e) {
        }

        $media->delete();

        return redirect()->route('admin.media.index')->with('success', 'Deleted.');
    }
}
