<?php

namespace App\Http\Controllers;

use App\Actions\GetFileUrl;
use App\Models\StoredFile;
use Illuminate\Http\RedirectResponse;

class FileController extends Controller
{
    /**
     * Redirect the owner to a short-lived signed URL for the file.
     */
    public function show(StoredFile $storedFile, GetFileUrl $getFileUrl): RedirectResponse
    {
        return redirect()->away($getFileUrl->handle($storedFile));
    }
}
