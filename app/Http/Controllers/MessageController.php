<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private ChatService $service) {}

    public function store(Request $request)
    {
        $this->service->sendMessage(
            auth()->user(),
            $request->conversation_id,
            $request->message
        );

        return back();
    }
}
