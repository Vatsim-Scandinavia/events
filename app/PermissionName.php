<?php

namespace App;

enum PermissionName: string
{
    case ViewEvents = 'events.view';
    case ManageEvents = 'events.manage';
    case ManageRoles = 'roles.manage';
    case ManageFirs = 'firs.manage';
}
