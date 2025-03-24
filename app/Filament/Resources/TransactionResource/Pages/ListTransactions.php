<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->icon('heroicon-s-user-group')
                ->modifyQueryUsing(fn(Builder $query) => $query->withTrashed())
                ->badge(Transaction::withTrashed()->count())
                ->badgeColor('warning'),

            'today' => Tab::make()
                ->icon('heroicon-s-calendar')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('created_at', now()))
                ->badge(Transaction::whereDate('created_at', now())->count())
                ->badgeColor('primary'),

            'active' => Tab::make()
                ->icon('heroicon-s-user')
                ->modifyQueryUsing(fn(Builder $query) => $query->withoutTrashed())
                ->badge(Transaction::withoutTrashed()->count())
                ->badgeColor('success'),

            'deleted' => Tab::make()
                ->icon('heroicon-s-trash')
                ->modifyQueryUsing(fn(Builder $query) => $query->onlyTrashed())
                ->badge(Transaction::onlyTrashed()->count())
                ->badgeColor('danger'),
        ];
    }
    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }
}
