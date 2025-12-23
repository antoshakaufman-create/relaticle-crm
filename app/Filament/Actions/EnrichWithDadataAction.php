<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Company;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class EnrichWithDadataAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'enrich_with_dadata';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('DaData Enrichment')
            ->icon('heroicon-m-identification')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Enrich with legal data (DaData)')
            ->modalDescription('This will fetch legal information like INN, OGRN, and CEO details from DaData.')
            ->action(function (Company $record) {
                $apiKey = 'd727a93a800dd5572305eb876d66c44c3099813a'; // Found in EnrichCompanyDetails command
    
                try {
                    $response = Http::withHeaders([
                        'Authorization' => 'Token ' . $apiKey,
                        'Accept' => 'application/json',
                    ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party', [
                                'query' => $record->name,
                                'count' => 1
                            ]);

                    if ($response->successful()) {
                        $json = $response->json();

                        if (!empty($json['suggestions'])) {
                            $data = $json['suggestions'][0]['data'];
                            $value = $json['suggestions'][0]['value'];

                            $record->legal_name = $value;
                            $record->inn = $data['inn'] ?? null;
                            $record->ogrn = $data['ogrn'] ?? null;
                            $record->kpp = $data['kpp'] ?? null;

                            if (isset($data['management'])) {
                                $record->management_name = $data['management']['name'] ?? null;
                                $record->management_post = $data['management']['post'] ?? null;
                            }

                            $record->okved = $data['okved'] ?? null;
                            $record->status = $data['state']['status'] ?? null;

                            if (empty($record->address_line_1) && isset($data['address']['value'])) {
                                $record->address_line_1 = $data['address']['value'];
                            }

                            $record->save();

                            Notification::make()
                                ->title('Enrichment Successful')
                                ->body("Legal data for '{$value}' updated.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No Data Found')
                                ->body('DaData could not find matching company for this name.')
                                ->warning()
                                ->send();
                        }
                    } else {
                        Notification::make()
                            ->title('API Error')
                            ->body('Failed to connect to DaData API. Status: ' . $response->status())
                            ->danger()
                            ->send();
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Error')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
