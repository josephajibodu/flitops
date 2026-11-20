<?php

namespace App\Filament\Resources;

use Filament\Resources\Pages\ViewRecord;

/**
 * A record's page in the admin, titled with just the record's name.
 */
abstract class ViewAdminRecord extends ViewRecord
{
    public function getTitle(): string
    {
        return $this->getRecordTitle();
    }
}
