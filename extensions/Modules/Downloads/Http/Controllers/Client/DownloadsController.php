<?php

namespace Extensions\Modules\Downloads\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Extensions\Modules\Downloads\Models\DownloadFile;
use Extensions\Modules\Downloads\Models\DownloadFolder;
use Illuminate\Validation\ValidationException;

class DownloadsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $folders = DownloadFolder::query()
            ->visible()
            ->ordered()
            ->with(['files' => fn ($query) => $query->published()->ordered()])
            ->get()
            ->filter(fn (DownloadFolder $folder) => $folder->isVisibleTo($user))
            ->values();

        return client_view('downloads::downloads.index', [
            'folders' => $folders,
        ]);
    }

    public function folder(DownloadFolder $folder)
    {
        $user = auth()->user();

        abort_unless($folder->isVisibleTo($user) || ($user?->isStaff() && $user->hasPermission('admin.downloads')), 404);

        return client_view('downloads::downloads.folder', [
            'folder' => $folder,
            'files' => $folder->filesVisibleTo($user),
        ]);
    }

    public function download(DownloadFolder $folder, DownloadFile $file)
    {
        abort_unless($file->folder_id === $folder->id, 404);

        $user = auth()->user();

        if (! $file->isVisibleTo($user) && ! $file->staffCanManage($user)) {
            abort(404);
        }

        try {
            return DownloadFile::actions()->streamFor(
                $user,
                $file,
                request()->ip(),
                request()->userAgent(),
            );
        } catch (ValidationException $exception) {
            abort(403, $exception->validator->errors()->first() ?: 'You cannot download this file.');
        }
    }
}
