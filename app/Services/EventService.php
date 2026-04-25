<?php

namespace App\Services;

use App\Models\Event;

class EventService
{
    public function create($data)
    {
        return Event::create($data);
    }

    public function update($event, $data)
    {
        return $event->update($data);
    }

    public function delete($event)
    {
        return $event->delete();
    }
}
