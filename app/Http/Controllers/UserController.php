<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('index', User::class);

        $users = User::all();

        return view('users.index', compact('users'));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        $events = $user->events()
            ->where('start_date', '>=', Carbon::now())
            ->orderBy('start_date', 'asc')
            ->get()
            ->filter(function($event) {
                return \Auth::user()->can('view', $event);
            });

        $areas = Area::all();

        $groups = Group::all();

        return view('users.show', compact('user', 'events', 'areas', 'groups'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $permissions = [];

        // Generate a list of possible validations
        foreach (Area::all() as $area) {
            foreach (Group::all() as $group) {
                // Only process ranks the user is allowed to change
                $this->authorize('updateGroup', [$user, $group, $area]);

                $key = $area->id . '_' . $group->name;
                $permissions[$key] = '';
            }
        }

        // Valiate and allow these fields, then loop through permissions to set the final data set
        $data = $request->validate($permissions);
        foreach ($permissions as $key => $value) {
            isset($data[$key]) ? $permissions[$key] = true : $permissions[$key] = false;
        }

        // Check and update the permissions
        foreach ($permissions as $key => $value) {
            $str = explode('_', $key);

            $area = Area::where('id', $str[0])->get()->first();
            $group = Group::where('name', $str[1])->get()->first();

            // Check if permission is not set, and set it or other way around.
            if ($user->groups()->where('area_id', $area->id)->where('group_id', $group->id)->get()->count() == 0) {
                if ($value == true) {
                    $this->authorize('updateGroup', [$user, $group, $area]);

                    // Attach the new permission
                    $user->groups()->attach($group, ['area_id' => $area->id, 'inserted_by' => \Auth::user()->id]);
                }
            } else {
                if ($value == false) {
                    $this->authorize('updateGroup', [$user, $group, $area]);

                    // Detach the permission
                    $user->groups()->wherePivot('area_id', $area->id)->wherePivot('group_id', $group->id)->detach();
                }
            }
        }

        return redirect()->route('users.index')->withSuccess('User successfully updated');
    }
}
