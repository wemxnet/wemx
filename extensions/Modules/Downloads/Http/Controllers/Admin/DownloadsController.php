<?php

namespace Extensions\Modules\Downloads\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Extensions\Modules\Downloads\Models\DownloadFile;
use Extensions\Modules\Downloads\Models\DownloadFolder;

class DownloadsController extends Controller
{
    public function index()
    {
        return admin_view('downloads::downloads.index');
    }

    public function createFolder()
    {
        return admin_view('downloads::downloads.folders.create');
    }

    public function showFolder(DownloadFolder $folder)
    {
        return admin_view('downloads::downloads.folders.show', [
            'folder' => $folder,
        ]);
    }

    public function editFolder(DownloadFolder $folder)
    {
        return admin_view('downloads::downloads.folders.edit', [
            'folder' => $folder,
        ]);
    }

    public function createFile(DownloadFolder $folder)
    {
        return admin_view('downloads::downloads.files.create', [
            'folder' => $folder,
        ]);
    }

    public function editFile(DownloadFile $file)
    {
        return admin_view('downloads::downloads.files.edit', [
            'file' => $file,
        ]);
    }
}
