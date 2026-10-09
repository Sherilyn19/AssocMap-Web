<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\CebuGeographySeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CebuGeographySeederTest extends TestCase
{
    public function test_reference_is_complete_linked_repeatable_and_preserves_existing_records(): void
    {
        if (getenv('ASSOCMAP_MEMBERSHIP_TESTS') !== '1') {
            $this->markTestSkipped('Enable isolated PostgreSQL tests.');
        }
        $schema = 'assocmap_test_geography_'.bin2hex(random_bytes(8));
        config(['database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema]);
        DB::purge('pgsql');
        DB::beginTransaction();
        try {
            DB::statement('CREATE SCHEMA "'.$schema.'"');
            DB::statement('SET LOCAL search_path TO "'.$schema.'"');
            DB::unprepared('CREATE TABLE area_units (id bigserial PRIMARY KEY, name varchar NOT NULL, province varchar, address text, is_archived boolean DEFAULT false, created_at timestamp, updated_at timestamp);
                CREATE TABLE sub_units (id bigserial PRIMARY KEY, area_unit_id bigint REFERENCES area_units(id), name varchar NOT NULL, is_archived boolean DEFAULT false, created_at timestamp, updated_at timestamp);');
            DB::table('area_units')->insert([
                ['name' => 'City of Cebu', 'province' => 'Cebu', 'address' => 'Existing address', 'is_archived' => false],
                ['name' => 'Alcantara', 'province' => 'Cebu', 'address' => 'Retained', 'is_archived' => true],
            ]);
            DB::table('sub_units')->insert(['area_unit_id' => 2, 'name' => 'Cabadiangan', 'is_archived' => true]);
            $existing = DB::table('area_units')->orderBy('id')->get()->toJson();
            $seed = new CebuGeographySeeder();
            $seed->run();
            $this->assertSame(53, DB::table('area_units')->count());
            $this->assertSame(159, DB::table('sub_units')->count());
            $this->assertSame($existing, DB::table('area_units')->whereIn('id', [1, 2])->orderBy('id')->get()->toJson());
            $this->assertTrue((bool) DB::table('sub_units')->where('id', 1)->value('is_archived'));
            $firstRun = DB::table('sub_units')->orderBy('id')->get()->toJson();
            $seed->run();
            $this->assertSame(53, DB::table('area_units')->count());
            $this->assertSame($firstRun, DB::table('sub_units')->orderBy('id')->get()->toJson());
            $data = json_decode(file_get_contents(database_path('seeders/data/cebu-geography.json')), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(44, array_filter($data['localities'], fn ($row) => $row['type'] === 'municipality'));
            $this->assertCount(9, array_filter($data['localities'], fn ($row) => $row['type'] === 'city'));
            $this->assertEqualsCanonicalizing(config('cebu-municipalities'), array_column($data['localities'], 'name'));
            foreach ($data['localities'] as $locality) {
                $this->assertCount(3, $locality['barangays']);
                foreach ($locality['barangays'] as $barangay) {
                    $this->assertSame(substr($locality['psgc_code'], 0, 7), substr($barangay['psgc_code'], 0, 7));
                }
            }
        } finally {
            DB::rollBack();
            DB::disconnect('pgsql');
        }
    }
}
