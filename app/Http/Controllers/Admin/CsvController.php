<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Character;
use App\Models\Curse;
use App\Models\Event;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvController extends Controller
{
    private array $typeConfig = [];

    public function __construct()
    {
        $this->typeConfig = [
            'characters' => [
                'model' => Character::class,
                'columns' => [
                    'id', 'name', 'description',
                    'dice', 'wild_value', 'wild_ability', 'wild_ability_description',
                    'dice_duel', 'wild_value_duel', 'wild_ability_duel', 'wild_ability_description_duel',
                    'addon_id', 'available_cooperative', 'available_duel', 'is_available',
                ],
                'json_fields' => ['dice', 'dice_duel'],
                'bool_fields' => ['available_cooperative', 'available_duel', 'is_available'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'description' => 'required|string',
                    'dice' => 'required|array|size:3',
                    'wild_value' => 'required|integer|min:1|max:10',
                    'wild_ability' => 'required|string|max:50',
                    'wild_ability_description' => 'nullable|string',
                    'dice_duel' => 'nullable|array',
                    'wild_value_duel' => 'nullable|integer|min:1|max:10',
                    'wild_ability_duel' => 'nullable|string|max:50',
                    'wild_ability_description_duel' => 'nullable|string',
                    'addon_id' => 'nullable|integer|exists:addons,id',
                    'available_cooperative' => 'boolean',
                    'available_duel' => 'boolean',
                    'is_available' => 'boolean',
                ],
            ],
            'cards' => [
                'model' => Card::class,
                'columns' => [
                    'id', 'title', 'description', 'sort_order',
                    'difficulty', 'positive_effects', 'negative_effects',
                    'difficulty_duel', 'positive_effects_duel', 'negative_effects_duel',
                    'positive_flavor', 'negative_flavor', 'category',
                    'available_cooperative', 'available_duel',
                ],
                'json_fields' => ['positive_effects', 'negative_effects', 'positive_effects_duel', 'negative_effects_duel'],
                'bool_fields' => ['available_cooperative', 'available_duel'],
                'rules' => [
                    'title' => 'required|string|max:255',
                    'description' => 'required|string',
                    'sort_order' => 'required|integer',
                    'difficulty' => 'required|integer|min:1|max:20',
                    'positive_effects' => 'required|array',
                    'negative_effects' => 'required|array',
                    'difficulty_duel' => 'nullable|integer|min:1|max:20',
                    'positive_effects_duel' => 'nullable|array',
                    'negative_effects_duel' => 'nullable|array',
                    'positive_flavor' => 'nullable|string',
                    'negative_flavor' => 'nullable|string',
                    'category' => 'nullable|string|max:100',
                    'available_cooperative' => 'boolean',
                    'available_duel' => 'boolean',
                ],
            ],
            'events' => [
                'model' => Event::class,
                'columns' => [
                    'id', 'title', 'effect',
                    'stat_modifiers', 'mechanic', 'mechanic_data',
                    'stat_modifiers_duel', 'mechanic_duel', 'mechanic_data_duel',
                    'addon_id', 'available_cooperative', 'available_duel',
                ],
                'json_fields' => ['stat_modifiers', 'mechanic_data', 'stat_modifiers_duel', 'mechanic_data_duel'],
                'bool_fields' => ['available_cooperative', 'available_duel'],
                'rules' => [
                    'title' => 'required|string|max:255',
                    'effect' => 'required|string',
                    'stat_modifiers' => 'nullable|array',
                    'mechanic' => 'nullable|string|in:stat_modifier,reduce_dice,grant_items,altered_deal,score_event',
                    'mechanic_data' => 'nullable|array',
                    'stat_modifiers_duel' => 'nullable|array',
                    'mechanic_duel' => 'nullable|string|in:stat_modifier,reduce_dice,grant_items,altered_deal,score_event',
                    'mechanic_data_duel' => 'nullable|array',
                    'addon_id' => 'nullable|integer|exists:addons,id',
                    'available_cooperative' => 'boolean',
                    'available_duel' => 'boolean',
                ],
            ],
            'items' => [
                'model' => Item::class,
                'columns' => [
                    'id', 'name', 'description',
                    'effect', 'effect_duel',
                    'effect_type', 'target',
                    'is_negative', 'is_consumable',
                    'addon_id', 'available_cooperative', 'available_duel',
                ],
                'json_fields' => ['effect', 'effect_duel'],
                'bool_fields' => ['is_negative', 'is_consumable', 'available_cooperative', 'available_duel'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'description' => 'required|string',
                    'effect' => 'required|array',
                    'effect_duel' => 'nullable|array',
                    'effect_type' => 'required|string|in:passive,active',
                    'target' => 'nullable|string',
                    'is_negative' => 'boolean',
                    'is_consumable' => 'boolean',
                    'addon_id' => 'nullable|integer|exists:addons,id',
                    'available_cooperative' => 'boolean',
                    'available_duel' => 'boolean',
                ],
            ],
            'curses' => [
                'model' => Curse::class,
                'columns' => [
                    'id', 'name', 'description',
                    'negative_effect', 'positive_effect',
                    'negative_effect_duel', 'positive_effect_duel',
                    'is_available',
                ],
                'json_fields' => ['negative_effect', 'positive_effect', 'negative_effect_duel', 'positive_effect_duel'],
                'bool_fields' => ['is_available'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'description' => 'required|string',
                    'negative_effect' => 'required|array',
                    'positive_effect' => 'required|array',
                    'negative_effect_duel' => 'nullable|array',
                    'positive_effect_duel' => 'nullable|array',
                    'is_available' => 'boolean',
                ],
            ],
        ];
    }

    public function export(string $type): StreamedResponse
    {
        if (!isset($this->typeConfig[$type])) {
            abort(404, "Unknown type: {$type}");
        }

        $config = $this->typeConfig[$type];
        $columns = $config['columns'];
        $jsonFields = $config['json_fields'];
        $records = $config['model']::all();

        return response()->streamDownload(function () use ($records, $columns, $jsonFields) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $columns);

            foreach ($records as $record) {
                $row = [];
                foreach ($columns as $col) {
                    $value = $record->getRawOriginal($col) ?? $record->getAttribute($col);
                    if (in_array($col, $jsonFields)) {
                        // Get the raw attribute to preserve JSON, or encode the cast value
                        $raw = $record->getRawOriginal($col);
                        $value = is_string($raw) ? $raw : json_encode($record->getAttribute($col));
                    }
                    $row[] = $value;
                }
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, "{$type}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request, string $type)
    {
        if (!isset($this->typeConfig[$type])) {
            abort(404, "Unknown type: {$type}");
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $config = $this->typeConfig[$type];
        $columns = $config['columns'];
        $jsonFields = $config['json_fields'];
        $boolFields = $config['bool_fields'];
        $rules = $config['rules'];
        $modelClass = $config['model'];

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        // Strip BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = str_getcsv($content, "\n");

        if (count($lines) < 2) {
            return response()->json(['created' => 0, 'updated' => 0, 'errors' => ['CSV file is empty or has no data rows.']], 422);
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map('trim', $header);

        // Validate header matches expected columns
        $missingColumns = array_diff(
            array_filter($columns, fn($c) => $c !== 'id'),
            $header
        );
        if (!empty($missingColumns)) {
            return response()->json([
                'created' => 0,
                'updated' => 0,
                'errors' => ['Missing required columns: ' . implode(', ', $missingColumns)],
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if ($line === '') continue;

            $values = str_getcsv($line);
            if (count($values) !== count($header)) {
                $errors[] = "Row " . ($lineNum + 2) . ": column count mismatch (expected " . count($header) . ", got " . count($values) . ")";
                continue;
            }

            $rowData = array_combine($header, $values);
            $id = isset($rowData['id']) && $rowData['id'] !== '' ? (int) $rowData['id'] : null;

            // Build the data array, parsing JSON fields and booleans
            $data = [];
            foreach ($columns as $col) {
                if ($col === 'id') continue;
                if (!array_key_exists($col, $rowData)) continue;

                $val = $rowData[$col];

                if (in_array($col, $jsonFields)) {
                    if ($val === '' || $val === null) {
                        $data[$col] = null;
                    } else {
                        $decoded = json_decode($val, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $errors[] = "Row " . ($lineNum + 2) . ": invalid JSON in column '{$col}'";
                            continue 2;
                        }
                        $data[$col] = $decoded;
                    }
                } elseif (in_array($col, $boolFields)) {
                    $data[$col] = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                } elseif ($val === '') {
                    $data[$col] = null;
                } else {
                    $data[$col] = $val;
                }
            }

            // Validate
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $msgs = collect($validator->errors()->all())->implode('; ');
                $errors[] = "Row " . ($lineNum + 2) . ": {$msgs}";
                continue;
            }

            if ($id && $modelClass::find($id)) {
                $modelClass::where('id', $id)->update($data);
                $updated++;
            } else {
                $modelClass::create($data);
                $created++;
            }
        }

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }
}
