<?php
namespace Database\Seeders;

use App\Models\WorkoutExercise;
use App\Models\WorkoutTaxonomyTerm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkoutExerciseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/workout_lower_body_v1.json')), true, 512, JSON_THROW_ON_ERROR);
        $labels = [
            'region' => ['lower_body' => 'Tren inferior'],
            'goal' => ['hypertrophy' => 'Hipertrofia'],
            'level' => ['intermediate' => 'Intermedio'],
            'demand' => ['knee_motion' => 'Movimiento de rodilla', 'hip_motion' => 'Movimiento de cadera', 'single_leg_work' => 'Trabajo unilateral de pierna', 'external_load' => 'Carga externa', 'ankle_motion' => 'Movimiento de tobillo'],
            'pattern' => ['knee_dominant' => 'Dominante de rodilla', 'knee_extension' => 'Extensión de rodilla', 'hip_hinge' => 'Bisagra de cadera',
                'hip_extension' => 'Extensión de cadera', 'knee_flexion' => 'Flexión de rodilla', 'hip_abduction' => 'Abducción de cadera',
                'hip_adduction' => 'Aducción de cadera', 'plantar_flexion' => 'Flexión plantar', 'dorsiflexion' => 'Dorsiflexión'],
            'muscle' => ['quadriceps' => 'Cuádriceps', 'glutes' => 'Glúteos', 'hamstrings' => 'Isquiotibiales', 'erectors' => 'Erectores espinales',
                'adductors' => 'Aductores', 'hip_abductors' => 'Abductores de cadera', 'gastrocnemius' => 'Gastrocnemio', 'soleus' => 'Sóleo', 'tibialis_anterior' => 'Tibial anterior'],
            'equipment' => ['bodyweight' => 'Sin equipo', 'barbell' => 'Barra y discos', 'rack' => 'Rack', 'box' => 'Cajón', 'dumbbell' => 'Mancuernas',
                'bench' => 'Banco', 'smith_machine' => 'Máquina Smith', 'hack_squat_machine' => 'Máquina hack squat', 'leg_press_machine' => 'Prensa',
                'belt_squat_machine' => 'Máquina belt squat', 'leg_extension_machine' => 'Máquina de extensión de rodilla', 'trap_bar' => 'Barra hexagonal',
                'cable_machine' => 'Polea', 'back_extension_bench' => 'Banco de extensiones', 'hip_thrust_machine' => 'Máquina hip thrust',
                'ankle_strap' => 'Tobillera para polea', 'seated_leg_curl_machine' => 'Curl femoral sentado', 'lying_leg_curl_machine' => 'Curl femoral tumbado',
                'standing_leg_curl_machine' => 'Curl femoral de pie', 'sliders' => 'Deslizadores', 'stability_ball' => 'Balón de estabilidad',
                'hip_abduction_machine' => 'Máquina de abducción', 'hip_adduction_machine' => 'Máquina de aducción',
                'standing_calf_machine' => 'Máquina de pantorrilla de pie', 'seated_calf_machine' => 'Máquina de pantorrilla sentado'],
        ];
        DB::transaction(function () use ($labels, $data) {
            $terms = [];
            foreach ($labels as $kind => $items) foreach ($items as $code => $label) {
                $terms[$kind][$code] = WorkoutTaxonomyTerm::firstOrCreate(['kind' => $kind, 'code' => $code], ['names' => ['es' => $label]])->id;
            }
            foreach ($data['exercises'] as $row) {
                $parent = $row['variant_of'] ? WorkoutExercise::where('code', 'lower_body_'.$row['variant_of'])->firstOrFail()->id : null;
                $exercise = WorkoutExercise::firstOrCreate(['code' => 'lower_body_'.$row['code']], [
                    'coach_id' => null, 'variant_of_id' => $parent, 'names' => $row['names'], 'load_mode' => $row['load_mode'],
                    'fatigue' => $row['fatigue'], 'unilateral' => $row['unilateral'], 'is_active' => true,
                    'review_status' => 'pending', 'seed_version' => $data['version'],
                ]);
                // A rerun never overwrites edits, reviews, activation or curated associations.
                if (!$exercise->wasRecentlyCreated) continue;
                $pivot = [];
                $groups = ['region' => ['lower_body'], 'goal' => ['hypertrophy'], 'level' => ['intermediate'],
                    'pattern' => [$row['pattern']], 'equipment' => $row['equipment']];
                $demands = [];
                if (in_array($row['pattern'], ['knee_dominant', 'knee_extension', 'knee_flexion'], true)) $demands[] = 'knee_motion';
                if (in_array($row['pattern'], ['hip_hinge', 'hip_extension', 'hip_abduction', 'hip_adduction'], true)) $demands[] = 'hip_motion';
                if (in_array($row['pattern'], ['plantar_flexion', 'dorsiflexion'], true)) $demands[] = 'ankle_motion';
                if ($row['unilateral']) $demands[] = 'single_leg_work';
                if ($row['load_mode'] !== 'bodyweight') $demands[] = 'external_load';
                $groups['demand'] = $demands;
                foreach ($groups as $kind => $codes) foreach ($codes as $code) $pivot[$terms[$kind][$code]] = ['role' => $kind === 'equipment' ? 'required' : 'classification'];
                foreach ($row['primary_muscles'] as $code) $pivot[$terms['muscle'][$code]] = ['role' => 'primary'];
                foreach ($row['secondary_muscles'] as $code) $pivot[$terms['muscle'][$code]] = ['role' => 'secondary'];
                $exercise->terms()->sync($pivot);
            }
        });
    }
}
