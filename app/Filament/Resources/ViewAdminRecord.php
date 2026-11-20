<?php

namespace App\Filament\Resources;

use Filament\Resources\Pages\ViewRecord;

/**
 * A record's page in the admin, titled like Shopify's: just the record's
 * name, with one breadcrumb back to its list.
 */
abstract class ViewAdminRecord extends ViewRecord
{
    public function getTitle(): string
    {
        return $this->getRecordTitle();
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        return [$resource::getUrl() => $resource::getBreadcrumb()];
    }
}
