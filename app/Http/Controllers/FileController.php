<?php

namespace App\Http\Controllers;

use App\Services\FileService;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(private FileService $service) {}

    public function store(Request $request)
    {
        $this->service->upload(
            auth()->user(),
            $request->file('file'),
            $request->club_id
        );

        return back();
    }
}
