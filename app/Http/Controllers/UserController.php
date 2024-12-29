<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
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
            ->orderBy('start_date', 'asc')
            ->get()
            ->filter(function ($event) {
                return \Auth::user()->can('view', $event);
            });

        $groups = Group::all();

        return view('users.show', compact('user', 'events', 'groups'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $permissions = [];

        // Generate a list of possible validations
        foreach (Group::all() as $group) {
            $permissions[$group->name] = '';
        }

        // Valiate and allow these fields, then loop through permissions to set the final data set
        $data = $request->validate($permissions);
        foreach ($permissions as $key => $value) {
            isset($data[$key]) ? $permissions[$key] = true : $permissions[$key] = false;
        }

        //dd($permissions);

        // Check and update the permissions
        foreach ($permissions as $key => $value) {
            $group = Group::where('name', $key)->get()->first();

            // Check if permission is not set, and set it or other way around.
            if ($user->groups()->where('group_id', $group->id)->get()->count() == 0) {
                if ($value == true) {
                    // Attach the new permission
                    $user->groups()->attach($group, ['inserted_by' => \Auth::user()->id]);
                }
            } else {
                if ($value == false) {
                    // Detach the permission
                    $user->groups()->wherePivot('group_id', $group->id)->detach();
                }
            }
        }

        return redirect()->route('users.show', $user)->withSuccess('User successfully updated');
    }
}
