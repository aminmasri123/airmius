<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private EventService $service) {}

    public function store(Request $request)
    {
        $this->service->create($request->all());
        return back();
    }

    public function update(Request $request, Event $event)
    {
        $this->service->update($event, $request->all());
        return back();
    }

    public function destroy(Event $event)
    {
        $this->service->delete($event);
        return back();
    }
}
