<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Add reference geography without renaming, restoring, or resetting existing records. */
final class CebuGeographySeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__.'/data/cebu-geography.json'), true, 512, JSON_THROW_ON_ERROR);
        $created = DB::transaction(function () use ($data): array {
            // Serialize seed runs and manual area writes while matching existing names.
            DB::statement('LOCK TABLE area_units, sub_units IN SHARE ROW EXCLUSIVE MODE');
            $areas = DB::table('area_units')->get()->groupBy(fn ($row) => $this->key($row->name));
            $barangays = DB::table('sub_units')->get()->groupBy('area_unit_id');
            $areaCount = 0;
            $newBarangays = [];
            $timestamp = now();

            foreach ($data['localities'] as $locality) {
                $matches = $areas->get($this->key($locality['name']), collect());
                if ($matches->count() > 1) {
                    throw new RuntimeException('Duplicate city/municipality names need review: '.$locality['name']);
                }
                $existing = $matches->first();
                if ($existing && filled($existing->province) && mb_strtolower(trim($existing->province)) !== 'cebu') {
                    throw new RuntimeException('An existing area belongs to another province: '.$locality['name']);
                }
                $id = $existing?->id;
                if (!$id) {
                    $id = DB::table('area_units')->insertGetId([
                        'name' => $locality['name'], 'province' => 'Cebu',
                        'address' => null, 'is_archived' => false,
                        'created_at' => $timestamp, 'updated_at' => $timestamp,
                    ]);
                    $areaCount++;
                }
                $names = $barangays->get($id, collect())
                    ->map(fn ($row) => mb_strtolower(trim($row->name)))->all();
                foreach ($locality['barangays'] as $barangay) {
                    if (!in_array(mb_strtolower(trim($barangay['name'])), $names, true)) {
                        $newBarangays[] = [
                            'area_unit_id' => $id, 'name' => $barangay['name'],
                            'is_archived' => false, 'created_at' => $timestamp, 'updated_at' => $timestamp,
                        ];
                    }
                }
            }
            if ($newBarangays) {
                DB::table('sub_units')->insert($newBarangays);
            }
            return [$areaCount, count($newBarangays)];
        });
        $this->command?->info("Cebu geography ready: {$created[0]} localities and {$created[1]} barangays added. Existing records preserved.");
    }

    private function key(string $name): string
    {
        // Match common city naming styles without changing the stored display name.
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        return preg_replace('/^city of | city$/u', '', $name);
    }
}
