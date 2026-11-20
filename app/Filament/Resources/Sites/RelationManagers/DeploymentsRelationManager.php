<?php

namespace App\Filament\Resources\Sites\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Resources\Deployments\DeploymentResource;
use App\Models\Deployment;
use Filament\Tables\Table;

/**
 * The site's deployments, newest first, each opening its log.
 */
class DeploymentsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'deployments';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns(DeploymentResource::columns(withSite: false))
            ->recordUrl(fn (Deployment $record): string => DeploymentResource::getUrl('view', ['record' => $record]));
    }
}
