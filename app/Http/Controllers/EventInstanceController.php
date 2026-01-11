<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventInstance;

class EventInstanceController extends Controller
{
    public function destroy(EventInstance $instance)
    {
        // Authorize the request (if you have policies)
        // $this->authorize('delete', $instance);

        $instance->delete();

        return back()->with('success', 'The specific occurrence has been removed.');
    }

    public function restore($id)
    {
        $instance = \App\Models\EventInstance::withTrashed()->findOrFail($id);
        $instance->restore();

        return back()->with('success', 'Occurrence restored to the schedule.');
    }
}
