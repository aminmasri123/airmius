<?php

namespace App\Http\Controllers;

use App\Services\PostService;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(private PostService $service) {}

    public function store(Request $request)
    {
        $this->service->create(auth()->user(), $request->all());
        return back();
    }
}
