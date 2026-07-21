<?php

// database/seeders/AssocMapDemoSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AssocMapDemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo@12345';

    public function run(): void
    {
        $this->assertRequiredTables();

        DB::transaction(function (): void {
            $lookups = $this->seedAndLoadLookups();
            $users = $this->seedFieldOfficers($lookups['roles']);
            $places = $this->seedCebuPlaces();
            $associations = $this->seedAssociations($places, $users, $lookups);
            $sharedUsers = $this->seedAssociationAccounts($associations, $lookups['roles']);
            $members = $this->seedMembers($associations, $lookups['sex']);
            $this->assignRepresentatives($associations, $members);
            $this->seedMemberApplications($associations, $members, $lookups);
            $projects = $this->seedProjects($associations, $lookups);
            $materials = $this->seedProjectMaterials($projects, $lookups['statuses']);
            $trainings = $this->seedTrainings($associations, $lookups['components']);
            $this->seedTrainingParticipants($trainings, $members, $lookups['statuses']);
            $this->seedMonitoring($associations, $projects, $materials, $users, $lookups);
            $this->seedGisLocations($associations);
            $this->seedAuditLogs($users, $associations, $projects);

            // Keeps variables intentionally referenced for clarity during maintenance.
            unset($sharedUsers);
        }, 3);

        $this->command?->info('AssocMap Filipino demo data seeded successfully.');
        $this->command?->line('Demo password for generated accounts: '.self::DEMO_PASSWORD);
    }

    private function assertRequiredTables(): void
    {
        $tables = [
            'roles', 'statuses', 'sex', 'program_components', 'quarters',
            'users', 'area_units', 'sub_units', 'associations', 'members',
            'member_applications', 'projects', 'project_materials', 'trainings',
            'training_participants', 'monitoring_production', 'monitoring_income',
            'monitoring_materials', 'gis_locations', 'audit_logs',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table is missing: {$table}");
            }
        }
    }

    private function seedAndLoadLookups(): array
    {
        $lookupValues = [
            'roles' => ['role_name' => [
                'System Administrator', 'Field Officer', 'Association Member',
            ]],
            'statuses' => ['status_name' => [
                'Active', 'Inactive', 'Archived', 'Pending', 'Approved', 'Rejected',
                'Ongoing', 'Completed', 'Planned', 'Good', 'Damaged', 'For Repair',
                'Present', 'Absent',
            ]],
            'sex' => ['sex_name' => ['Male', 'Female']],
            'program_components' => ['name' => [
                'Aquaculture', 'Capture Fisheries', 'Post-Harvest',
            ]],
            'quarters' => ['quarter_name' => ['Q1', 'Q2', 'Q3', 'Q4']],
        ];

        foreach ($lookupValues as $table => $definition) {
            $column = array_key_first($definition);

            foreach ($definition[$column] as $value) {
                $exists = DB::table($table)->where($column, $value)->exists();

                if (! $exists) {
                    DB::table($table)->insert([
                        $column => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return [
            'roles' => DB::table('roles')->pluck('id', 'role_name')->map(fn ($id) => (int) $id)->all(),
            'statuses' => DB::table('statuses')->pluck('id', 'status_name')->map(fn ($id) => (int) $id)->all(),
            'sex' => DB::table('sex')->pluck('id', 'sex_name')->map(fn ($id) => (int) $id)->all(),
            'components' => DB::table('program_components')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all(),
            'quarters' => DB::table('quarters')->pluck('id', 'quarter_name')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    private function seedFieldOfficers(array $roles): array
    {
        $fieldOfficerRoleId = $roles['Field Officer'];

        $accounts = [
            ['name' => 'Genevieve Flores', 'email' => 'gennie@bfar.gov.ph'],
            ['name' => 'Ramon Villanueva', 'email' => 'ramon.villanueva@bfar.gov.ph'],
            ['name' => 'Luzviminda Santos', 'email' => 'luzviminda.santos@bfar.gov.ph'],
            ['name' => 'Eduardo Manalili', 'email' => 'eduardo.manalili@bfar.gov.ph'],
        ];

        $result = [];

        foreach ($accounts as $account) {
            $result[] = $this->upsertUser(
                $account['name'],
                $account['email'],
                $fieldOfficerRoleId,
                null
            );
        }

        return $result;
    }

    private function seedCebuPlaces(): array
    {
        $places = [
            'Daanbantayan' => ['Maya', 'Tapilon'],
            'San Remigio' => ['Hagnaya', 'Tambongon'],
            'Tabogon' => ['Salag', 'Poblacion'],
            'Sogod' => ['Bagatayam', 'Poblacion'],
            'Argao' => ['Talo-ot', 'Poblacion'],
            'Dalaguete' => ['Obong', 'Poblacion'],
            'Samboan' => ['Tangbo', 'Poblacion'],
            'Santander' => ['Liloan', 'Poblacion'],
        ];

        $result = [];

        foreach ($places as $municipality => $barangays) {
            $areaUnitId = $this->firstOrCreateWithTimestamps(
                'area_units',
                ['name' => $municipality],
                [
                    'province' => 'Cebu',
                    'address' => "Municipal Hall, {$municipality}, Cebu",
                    'is_archived' => false,
                ]
            );

            $result[$municipality] = [
                'area_unit_id' => $areaUnitId,
                'barangays' => [],
            ];

            foreach ($barangays as $barangay) {
                $subUnitId = $this->firstOrCreateWithTimestamps(
                    'sub_units',
                    [
                        'area_unit_id' => $areaUnitId,
                        'name' => $barangay,
                    ],
                    ['is_archived' => false]
                );

                $result[$municipality]['barangays'][$barangay] = $subUnitId;
            }
        }

        return $result;
    }

    private function seedAssociations(array $places, array $fieldOfficers, array $lookups): array
    {
        $rows = [
            [
                'name' => 'Maya Bantay-Dagat at Mangingisda Association',
                'municipality' => 'Daanbantayan', 'barangay' => 'Maya',
                'component' => 'Capture Fisheries', 'field_officer' => 0,
                'date_joined' => '2023-02-15',
            ],
            [
                'name' => 'Hagnaya Sama-Samang Mangingisda',
                'municipality' => 'San Remigio', 'barangay' => 'Hagnaya',
                'component' => 'Post-Harvest', 'field_officer' => 1,
                'date_joined' => '2023-04-10',
            ],
            [
                'name' => 'Salag Blue Sea Fisherfolk Group',
                'municipality' => 'Tabogon', 'barangay' => 'Salag',
                'component' => 'Aquaculture', 'field_officer' => 2,
                'date_joined' => '2023-06-21',
            ],
            [
                'name' => 'Bagatayam Asenso Mangingisda Association',
                'municipality' => 'Sogod', 'barangay' => 'Bagatayam',
                'component' => 'Capture Fisheries', 'field_officer' => 3,
                'date_joined' => '2023-08-05',
            ],
            [
                'name' => 'Talo-ot Kabuhayan sa Dagat Association',
                'municipality' => 'Argao', 'barangay' => 'Talo-ot',
                'component' => 'Post-Harvest', 'field_officer' => 0,
                'date_joined' => '2024-01-18',
            ],
            [
                'name' => 'Obong Mangingisda at Seaweed Growers',
                'municipality' => 'Dalaguete', 'barangay' => 'Obong',
                'component' => 'Aquaculture', 'field_officer' => 1,
                'date_joined' => '2024-03-12',
            ],
            [
                'name' => 'Tangbo Abante Coastal Fisherfolk Association',
                'municipality' => 'Samboan', 'barangay' => 'Tangbo',
                'component' => 'Capture Fisheries', 'field_officer' => 2,
                'date_joined' => '2024-05-25',
            ],
            [
                'name' => 'Liloan Southern Cebu Mangingisda Association',
                'municipality' => 'Santander', 'barangay' => 'Liloan',
                'component' => 'Post-Harvest', 'field_officer' => 3,
                'date_joined' => '2024-07-14',
            ],
        ];

        $result = [];

        foreach ($rows as $index => $row) {
            $areaUnitId = $places[$row['municipality']]['area_unit_id'];
            $subUnitId = $places[$row['municipality']]['barangays'][$row['barangay']];

            $associationId = $this->firstOrCreateWithTimestamps(
                'associations',
                [
                    'name' => $row['name'],
                    'area_unit_id' => $areaUnitId,
                ],
                [
                    'sub_unit_id' => $subUnitId,
                    'program_component_id' => $lookups['components'][$row['component']],
                    'field_officer_id' => $fieldOfficers[$row['field_officer']],
                    'status_id' => $index === 7
                        ? $lookups['statuses']['Inactive']
                        : $lookups['statuses']['Active'],
                    'address' => "Barangay {$row['barangay']}, {$row['municipality']}, Cebu",
                    'date_joined' => $row['date_joined'],
                    'is_archived' => false,
                ]
            );

            $result[] = array_merge($row, [
                'id' => $associationId,
                'area_unit_id' => $areaUnitId,
                'sub_unit_id' => $subUnitId,
            ]);
        }

        return $result;
    }

    private function seedAssociationAccounts(array $associations, array $roles): array
    {
        $slugs = [
            'maya', 'hagnaya', 'salag', 'bagatayam',
            'taloot', 'obong', 'tangbo', 'liloan-santander',
        ];

        $result = [];

        foreach ($associations as $index => $association) {
            $result[$association['id']] = $this->upsertUser(
                $association['name'],
                $slugs[$index].'@assocmap.test',
                $roles['Association Member'],
                $association['id']
            );
        }

        return $result;
    }

    private function seedMembers(array $associations, array $sex): array
    {
        $people = [
            ['Jose', 'Reyes', 'Dela Cruz', 'Male', '1978-03-14'],
            ['Maria', 'Santos', 'Garcia', 'Female', '1982-07-09'],
            ['Pedro', 'Flores', 'Mendoza', 'Male', '1975-11-22'],
            ['Lorna', 'Castillo', 'Ramos', 'Female', '1985-01-18'],
            ['Roberto', 'Navarro', 'Torres', 'Male', '1980-05-27'],
            ['Nestor', 'Aquino', 'Villanueva', 'Male', '1976-09-02'],
            ['Carmela', 'Bautista', 'Santos', 'Female', '1984-12-11'],
            ['Rogelio', 'Mercado', 'Fernandez', 'Male', '1972-04-08'],
            ['Imelda', 'Soriano', 'Lopez', 'Female', '1988-06-19'],
            ['Danilo', 'Pascual', 'Cabrera', 'Male', '1981-10-03'],
            ['Edgar', 'Manalo', 'Rivera', 'Male', '1979-02-25'],
            ['Marites', 'Valdez', 'Gonzales', 'Female', '1986-08-13'],
            ['Renato', 'Lim', 'Castro', 'Male', '1974-05-16'],
            ['Nenita', 'Rosales', 'Aguilar', 'Female', '1983-09-24'],
            ['Arnel', 'Domingo', 'Salazar', 'Male', '1987-01-07'],
            ['Ricardo', 'Ocampo', 'De Leon', 'Male', '1977-07-30'],
            ['Elena', 'Padilla', 'Morales', 'Female', '1989-03-05'],
            ['Samuel', 'Evangelista', 'Tolentino', 'Male', '1980-12-29'],
            ['Gloria', 'Vergara', 'Natividad', 'Female', '1982-04-17'],
            ['Armando', 'Macaraeg', 'Lorenzo', 'Male', '1973-08-21'],
            ['Feliciano', 'Abad', 'Pineda', 'Male', '1976-06-06'],
            ['Rosario', 'Magsaysay', 'Mercado', 'Female', '1985-10-15'],
            ['Leonardo', 'Lacsamana', 'Santiago', 'Male', '1979-01-26'],
            ['Teresita', 'Mabini', 'Dominguez', 'Female', '1987-05-12'],
            ['Benjamin', 'Bonifacio', 'Alvarez', 'Male', '1971-11-04'],
            ['Andres', 'Jacinto', 'Valencia', 'Male', '1978-09-18'],
            ['Corazon', 'Rizal', 'Manuel', 'Female', '1984-02-23'],
            ['Manuel', 'Luna', 'Esquivel', 'Male', '1975-06-28'],
            ['Lourdes', 'Del Pilar', 'Macapagal', 'Female', '1981-12-02'],
            ['Rolando', 'Silang', 'Buenaventura', 'Male', '1986-04-20'],
            ['Fernando', 'Katipunan', 'Abella', 'Male', '1977-10-10'],
            ['Josefina', 'Malvar', 'Delos Reyes', 'Female', '1988-01-31'],
            ['Marcelo', 'Aguinaldo', 'Tiongson', 'Male', '1974-03-19'],
            ['Aurora', 'Laurel', 'Dimaano', 'Female', '1983-07-07'],
            ['Isagani', 'Osmena', 'Cabahug', 'Male', '1980-09-26'],
            ['Teodoro', 'Roxas', 'Fajardo', 'Male', '1972-05-09'],
            ['Remedios', 'Quirino', 'Yap', 'Female', '1986-11-17'],
            ['Vicente', 'Recto', 'Go', 'Male', '1979-06-14'],
            ['Milagros', 'Quezon', 'Tan', 'Female', '1985-08-28'],
            ['Gregorio', 'Sumulong', 'Co', 'Male', '1976-12-06'],
        ];

        $roles = ['Pangulo', 'Bise-Presidente', 'Kalihim', 'Ingat-Yaman', 'Miyembro'];
        $beneficiaries = ['Rehistradong Mangingisda', 'Women Fisherfolk', 'Youth Fisherfolk'];
        $result = [];
        $personIndex = 0;

        foreach ($associations as $associationIndex => $association) {
            $result[$association['id']] = [];

            for ($memberIndex = 0; $memberIndex < 5; $memberIndex++) {
                [$first, $middle, $last, $sexName, $birthday] = $people[$personIndex++];

                $memberId = $this->firstOrCreateWithTimestamps(
                    'members',
                    [
                        'association_id' => $association['id'],
                        'first_name' => $first,
                        'middle_name' => $middle,
                        'last_name' => $last,
                        'birthday' => $birthday,
                    ],
                    [
                        'application_id' => null,
                        'user_id' => null,
                        'sex_id' => $sex[$sexName],
                        'role_in_assoc' => $roles[$memberIndex],
                        'beneficiary_type' => $beneficiaries[($associationIndex + $memberIndex) % count($beneficiaries)],
                        'contact_number' => sprintf('09%09d', 170000001 + $personIndex),
                        'address' => "Barangay {$association['barangay']}, {$association['municipality']}, Cebu",
                        'date_registered' => date('Y-m-d', strtotime($association['date_joined'].' +30 days')),
                        'is_archived' => false,
                    ]
                );

                $result[$association['id']][] = $memberId;
            }
        }

        return $result;
    }

    private function assignRepresentatives(array $associations, array $members): void
    {
        foreach ($associations as $association) {
            DB::table('associations')
                ->where('id', $association['id'])
                ->update([
                    'representative_member_id' => $members[$association['id']][0],
                    'updated_at' => now(),
                ]);
        }
    }

    private function seedMemberApplications(array $associations, array $members, array $lookups): void
    {
        $applicants = [
            ['Jun', 'Abellanosa', 'Ricarte', 'Male', '1991-02-12'],
            ['Analyn', 'Caballes', 'Montejo', 'Female', '1994-06-08'],
            ['Ramil', 'Dumlao', 'Amor', 'Male', '1989-10-21'],
            ['Sheila', 'Labra', 'Cabatingan', 'Female', '1993-04-16'],
            ['Mario', 'Alcantara', 'Canete', 'Male', '1988-07-03'],
            ['Jocelyn', 'Abenoja', 'Daclan', 'Female', '1990-11-27'],
            ['Rene', 'Bacalso', 'Ceniza', 'Male', '1987-01-09'],
            ['Maricel', 'Carreon', 'Enriquez', 'Female', '1995-09-14'],
            ['Joel', 'Dacua', 'Gabuya', 'Male', '1992-03-25'],
            ['Liza', 'Estenzo', 'Heredia', 'Female', '1996-12-04'],
            ['Noel', 'Fernan', 'Ilustrisimo', 'Male', '1986-05-18'],
            ['Rhea', 'Gica', 'Jumao-as', 'Female', '1991-08-30'],
            ['Allan', 'Hontiveros', 'Labella', 'Male', '1989-02-06'],
            ['Mercy', 'Jaca', 'Mabatid', 'Female', '1994-10-11'],
            ['Dennis', 'Kintanar', 'Ouano', 'Male', '1988-06-22'],
            ['Grace', 'Llamedo', 'Patalinghug', 'Female', '1993-01-19'],
        ];

        $applicantIndex = 0;

        foreach ($associations as $associationIndex => $association) {
            for ($applicationIndex = 0; $applicationIndex < 2; $applicationIndex++) {
                [$first, $middle, $last, $sexName, $birthday] = $applicants[$applicantIndex++];

                $statusName = $applicationIndex === 0
                    ? 'Pending'
                    : ($associationIndex % 2 === 0 ? 'Rejected' : 'Approved');

                $reviewed = $statusName !== 'Pending';
                $representativeId = $members[$association['id']][0];

                $applicationId = $this->firstOrCreateWithTimestamps(
                    'member_applications',
                    [
                        'association_id' => $association['id'],
                        'first_name' => $first,
                        'middle_name' => $middle,
                        'last_name' => $last,
                        'birthday' => $birthday,
                    ],
                    [
                        'sex_id' => $lookups['sex'][$sexName],
                        'beneficiary_type' => 'Rehistradong Mangingisda',
                        'contact_number' => sprintf('09%09d', 180000001 + $applicantIndex),
                        'address' => "Barangay {$association['barangay']}, {$association['municipality']}, Cebu",
                        'status_id' => $lookups['statuses'][$statusName],
                        'reviewed_by_member_id' => $reviewed ? $representativeId : null,
                        'reviewed_at' => $reviewed ? '2025-02-15 09:30:00' : null,
                        'rejection_reason' => $statusName === 'Rejected'
                            ? 'Kulang ang kalakip na patunay ng pagiging rehistradong mangingisda.'
                            : null,
                    ]
                );

                if ($statusName === 'Approved') {
                    $memberId = $this->firstOrCreateWithTimestamps(
                        'members',
                        [
                            'association_id' => $association['id'],
                            'first_name' => $first,
                            'middle_name' => $middle,
                            'last_name' => $last,
                            'birthday' => $birthday,
                        ],
                        [
                            'application_id' => $applicationId,
                            'user_id' => null,
                            'sex_id' => $lookups['sex'][$sexName],
                            'role_in_assoc' => 'Miyembro',
                            'beneficiary_type' => 'Rehistradong Mangingisda',
                            'contact_number' => sprintf('09%09d', 180000001 + $applicantIndex),
                            'address' => "Barangay {$association['barangay']}, {$association['municipality']}, Cebu",
                            'date_registered' => '2025-02-15',
                            'is_archived' => false,
                        ]
                    );

                    DB::table('members')->where('id', $memberId)->update([
                        'application_id' => $applicationId,
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function seedProjects(array $associations, array $lookups): array
    {
        $projectRows = [
            ['Community Fishing Gear Support', 'Lambat at basic fishing gear', 'Ongoing', 350000],
            ['Hagnaya Fish Processing and Packaging', 'Dried fish and fish packaging', 'Completed', 420000],
            ['Salag Seaweed Grow-Out Project', 'Kappaphycus seaweed', 'Ongoing', 510000],
            ['Bagatayam Municipal Fishing Support', 'Hook-and-line fisheries', 'Planned', 300000],
            ['Talo-ot Fish Drying and Marketing', 'Dried fish products', 'Ongoing', 465000],
            ['Obong Seaweed Nursery Project', 'Seaweed seedlings', 'Completed', 550000],
            ['Tangbo Small Fishing Boat Assistance', 'Municipal fishing boat', 'Ongoing', 600000],
            ['Liloan Cold Storage Starter Project', 'Chilled fish storage', 'Planned', 750000],
        ];

        $result = [];

        foreach ($associations as $index => $association) {
            [$title, $commodity, $status, $budget] = $projectRows[$index];

            $projectId = $this->firstOrCreateWithTimestamps(
                'projects',
                [
                    'association_id' => $association['id'],
                    'title' => $title,
                ],
                [
                    'commodity_type' => $commodity,
                    'program_component_id' => $lookups['components'][$association['component']],
                    'implementation_date' => date('Y-m-d', strtotime($association['date_joined'].' +90 days')),
                    'budget' => $budget,
                    'status_id' => $lookups['statuses'][$status],
                    'remarks' => 'Demo record para sa system testing at capstone presentation.',
                    'is_archived' => false,
                ]
            );

            $result[$association['id']] = $projectId;
        }

        return $result;
    }

    private function seedProjectMaterials(array $projects, array $statuses): array
    {
        $items = [
            ['Fishing net set', 12, 'set', 8500],
            ['Insulated fish box', 20, 'unit', 3200],
            ['Seaweed seedling line', 30, 'bundle', 1500],
            ['Marine rope', 40, 'roll', 2100],
            ['Fish drying rack', 10, 'unit', 6800],
            ['Vacuum sealer', 5, 'unit', 12500],
            ['Small fishing boat', 3, 'unit', 95000],
            ['Life vest', 25, 'piece', 1800],
            ['Digital weighing scale', 8, 'unit', 4500],
            ['Plastic crate', 40, 'piece', 750],
            ['Seaweed drying mat', 20, 'piece', 1100],
            ['Ice chest', 12, 'unit', 5200],
            ['Hook-and-line kit', 18, 'set', 2400],
            ['Outboard motor', 2, 'unit', 78000],
            ['Stainless work table', 4, 'unit', 16000],
            ['Chest freezer', 2, 'unit', 38000],
        ];

        $result = [];
        $itemIndex = 0;

        foreach ($projects as $associationId => $projectId) {
            $result[$associationId] = [];

            for ($i = 0; $i < 2; $i++) {
                [$name, $quantity, $unit, $unitCost] = $items[$itemIndex++];

                $materialId = $this->firstOrCreateWithTimestamps(
                    'project_materials',
                    [
                        'project_id' => $projectId,
                        'item_name' => $name,
                    ],
                    [
                        'quantity' => $quantity,
                        'unit' => $unit,
                        'unit_cost' => $unitCost,
                        'status_id' => $statuses['Good'],
                        'delivery_date' => '2025-01-20',
                    ]
                );

                $result[$associationId][] = $materialId;
            }
        }

        return $result;
    }

    private function seedTrainings(array $associations, array $components): array
    {
        $titles = [
            'Ligtas at Responsableng Pangingisda Training',
            'Fish Processing at Tamang Packaging Workshop',
            'Seaweed Farming Basics at Farm Maintenance',
            'Basic Boat Safety at Weather Awareness',
            'Tamang Pagpapatuyo at Pag-iimbak ng Isda',
            'Seaweed Seedling Selection at Disease Prevention',
            'Bangka Maintenance at Safety Orientation',
            'Cold Chain Basics para sa Fresh Fish',
        ];

        $result = [];

        foreach ($associations as $index => $association) {
            $trainingId = $this->firstOrCreateWithTimestamps(
                'trainings',
                [
                    'association_id' => $association['id'],
                    'title' => $titles[$index],
                ],
                [
                    'program_component_id' => $components[$association['component']],
                    'training_type' => 'Skills Training at Orientation',
                    'venue' => "Barangay Hall, {$association['barangay']}, {$association['municipality']}, Cebu",
                    'date_conducted' => '2025-03-10',
                    'training_cost' => 25000 + ($index * 2500),
                    'conducted_by' => 'BFAR Region VII - SAAD Program',
                    'remarks' => 'Natapos ang training na may aktibong partisipasyon ng mga miyembro.',
                    'is_archived' => false,
                ]
            );

            $result[$association['id']] = $trainingId;
        }

        return $result;
    }

    private function seedTrainingParticipants(
        array $trainings,
        array $members,
        array $statuses
    ): void {
        foreach ($trainings as $associationId => $trainingId) {
            foreach (array_slice($members[$associationId], 0, 3) as $index => $memberId) {
                DB::table('training_participants')->updateOrInsert(
                    [
                        'training_id' => $trainingId,
                        'member_id' => $memberId,
                    ],
                    [
                        'attendance_status_id' => $index === 2
                            ? $statuses['Absent']
                            : $statuses['Present'],
                    ]
                );
            }
        }
    }

    private function seedMonitoring(
        array $associations,
        array $projects,
        array $materials,
        array $fieldOfficers,
        array $lookups
    ): void {
        foreach ($associations as $index => $association) {
            $associationId = $association['id'];
            $projectId = $projects[$associationId];
            $createdBy = $fieldOfficers[$index % count($fieldOfficers)];

            DB::table('monitoring_production')->updateOrInsert(
                [
                    'association_id' => $associationId,
                    'project_id' => $projectId,
                    'quarter_id' => $lookups['quarters']['Q1'],
                    'year' => 2025,
                ],
                [
                    'target_output' => 1200 + ($index * 100),
                    'actual_output' => 980 + ($index * 95),
                    'remarks' => 'Maayos ang produksyon ngunit kailangan pang palakasin ang market linkage.',
                    'created_by' => $createdBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('monitoring_income')->updateOrInsert(
                [
                    'association_id' => $associationId,
                    'project_id' => $projectId,
                    'month' => 3,
                    'year' => 2025,
                ],
                [
                    'gross_income' => 85000 + ($index * 7500),
                    'remarks' => 'Kita mula sa bentahan sa lokal na merkado at mga suking buyer.',
                    'created_by' => $createdBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $conditionNames = ['Good', 'Damaged', 'For Repair'];
            $conditionName = $conditionNames[$index % count($conditionNames)];
            $projectMaterialId = $materials[$associationId][0];

            $existingId = DB::table('monitoring_materials')
                ->where('project_material_id', $projectMaterialId)
                ->value('id');

            $payload = [
                'material_description' => 'Pangunahing kagamitan na mino-monitor ng Field Officer.',
                'condition_status_id' => $lookups['statuses'][$conditionName],
                'scheduled_maintenance' => '2025-06-15',
                'actual_maintenance' => $conditionName === 'Good' ? null : '2025-06-20',
                'remarks' => $conditionName === 'Good'
                    ? 'Maayos at ginagamit nang tama.'
                    : 'Naitala para sa pagkukumpuni at follow-up inspection.',
                'created_by' => $createdBy,
                'updated_at' => now(),
            ];

            if ($existingId) {
                DB::table('monitoring_materials')->where('id', $existingId)->update($payload);
            } else {
                DB::table('monitoring_materials')->insert(array_merge($payload, [
                    'project_material_id' => $projectMaterialId,
                    'created_at' => now(),
                ]));
            }
        }
    }

    private function seedGisLocations(array $associations): void
    {
        $coordinates = [
            [11.2745, 124.0524, 'Maya Coastal Livelihood Site'],
            [11.1027, 123.9465, 'Hagnaya Fish Landing Area'],
            [10.9408, 124.0150, 'Salag Seaweed Production Site'],
            [10.7478, 124.0038, 'Bagatayam Fisherfolk Project Area'],
            [9.8740, 123.6025, 'Talo-ot Post-Harvest Site'],
            [9.7495, 123.5328, 'Obong Seaweed Nursery Area'],
            [9.5270, 123.3150, 'Tangbo Municipal Fishing Site'],
            [9.4255, 123.3380, 'Liloan Cold Storage Site'],
        ];

        foreach ($associations as $index => $association) {
            [$latitude, $longitude, $locationName] = $coordinates[$index];

            $existingId = DB::table('gis_locations')
                ->where('association_id', $association['id'])
                ->where('location_name', $locationName)
                ->value('id');

            $payload = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'is_published' => $index !== 7,
                'updated_at' => now(),
            ];

            if ($existingId) {
                DB::table('gis_locations')->where('id', $existingId)->update($payload);
            } else {
                DB::table('gis_locations')->insert(array_merge($payload, [
                    'association_id' => $association['id'],
                    'location_name' => $locationName,
                    'created_at' => now(),
                ]));
            }
        }
    }

    private function seedAuditLogs(array $fieldOfficers, array $associations, array $projects): void
    {
        foreach ($associations as $index => $association) {
            $userId = $fieldOfficers[$index % count($fieldOfficers)];
            $details = "Demo data: created association {$association['name']}";

            if (! DB::table('audit_logs')->where([
                'user_id' => $userId,
                'action_type' => 'CREATE',
                'module' => 'Association',
                'record_id' => $association['id'],
                'details' => $details,
            ])->exists()) {
                DB::table('audit_logs')->insert([
                    'user_id' => $userId,
                    'action_type' => 'CREATE',
                    'module' => 'Association',
                    'record_id' => $association['id'],
                    'details' => $details,
                    'performed_at' => now(),
                ]);
            }

            $projectId = $projects[$association['id']];
            $projectDetails = 'Demo data: project monitoring record prepared.';

            if (! DB::table('audit_logs')->where([
                'user_id' => $userId,
                'action_type' => 'MONITOR',
                'module' => 'Project',
                'record_id' => $projectId,
                'details' => $projectDetails,
            ])->exists()) {
                DB::table('audit_logs')->insert([
                    'user_id' => $userId,
                    'action_type' => 'MONITOR',
                    'module' => 'Project',
                    'record_id' => $projectId,
                    'details' => $projectDetails,
                    'performed_at' => now(),
                ]);
            }
        }
    }

    private function upsertUser(
        string $name,
        string $email,
        int $roleId,
        ?int $associationId
    ): int {
        $existingId = DB::table('users')->where('email', $email)->value('id');

        $payload = [
            'name' => $name,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'role_id' => $roleId,
            'association_id' => $associationId,
            'is_active' => true,
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('users')->where('id', $existingId)->update($payload);
            return (int) $existingId;
        }

        return (int) DB::table('users')->insertGetId(array_merge($payload, [
            'email' => $email,
            'created_at' => now(),
        ]));
    }

    private function firstOrCreateWithTimestamps(
        string $table,
        array $identity,
        array $values
    ): int {
        $query = DB::table($table);

        foreach ($identity as $column => $value) {
            $query->where($column, $value);
        }

        $existingId = $query->value('id');
        $payload = array_merge($values, ['updated_at' => now()]);

        if ($existingId) {
            DB::table($table)->where('id', $existingId)->update($payload);
            return (int) $existingId;
        }

        return (int) DB::table($table)->insertGetId(array_merge(
            $identity,
            $payload,
            ['created_at' => now()]
        ));
    }
}
