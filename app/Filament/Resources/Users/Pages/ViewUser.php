<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\ViewAdminRecord;

class ViewUser extends ViewAdminRecord
{
    protected static string $resource = UserResource::class;
}
