<?php

namespace App\Http\Controllers;

use App\Services\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['project_id' => 'required|integer', 'file' => 'required|file|mimes:pdf,docx,xlsx,csv,txt,jpg,jpeg,png,webp,zip|max:10240']);
        ProjectAccess::find($request->user(), $data['project_id']);
        $file = $request->file('file');
        $path = $file->store('projects/'.$data['project_id'], 'local');
        try {
            $id = DB::table('project_files')->insertGetId(['project_id' => $data['project_id'], 'user_id' => $request->user()->id, 'name' => basename($file->getClientOriginalName()), 'path' => $path, 'size' => $file->getSize(), 'mime' => $file->getMimeType(), 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return response()->json(['id' => $id], 201);
    }

    public function download(Request $request, int $id)
    {
        $file = DB::table('project_files')->find($id);
        abort_unless($file, 404);
        ProjectAccess::find($request->user(), $file->project_id);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, int $id)
    {
        $file = DB::table('project_files')->find($id);
        abort_unless($file, 404);
        ProjectAccess::find($request->user(), $file->project_id);
        abort_unless($request->user()->isAdmin() || $file->user_id === $request->user()->id, 403);
        Storage::disk('local')->delete($file->path);
        DB::table('project_files')->where('id', $id)->delete();

        return response()->json(['message' => 'File removed.']);
    }
}
