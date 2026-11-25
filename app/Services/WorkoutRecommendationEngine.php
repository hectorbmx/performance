<?php
namespace App\Services;

class WorkoutRecommendationEngine
{
    /** Input is prefiltered/authorized. Stable tie-break is the immutable code. */
    public function rank(array $candidates, array $selected, array $preferredIds, array $rules): array
    {
        $patterns = []; $muscles = []; $fatigue = 0; $families = [];
        foreach ($selected as $exercise) {
            foreach ($exercise['patterns'] as $pattern) $patterns[$pattern] = true;
            foreach ($exercise['primary_muscles'] as $muscle) $muscles[$muscle] = ($muscles[$muscle] ?? 0) + $rules['sets_per_exercise'];
            $fatigue += $rules['fatigue_cost'][$exercise['fatigue']];
            $families[$exercise['family']] = true;
        }
        $last = $selected ? $selected[array_key_last($selected)] : null;
        $complements = [];
        foreach ($last['patterns'] ?? [] as $pattern) $complements = array_merge($complements, $rules['complements'][$pattern] ?? []);
        $ranked = [];
        foreach ($candidates as $candidate) {
            $parts = []; $reasons = [];
            $newPattern = count(array_diff($candidate['patterns'], array_keys($patterns))) > 0;
            $parts['pattern'] = $rules['weights'][$newPattern ? 'new_pattern' : 'repeated_pattern'];
            $reasons[] = $newPattern ? 'Añade un patrón de movimiento distinto.' : 'Repite un patrón ya seleccionado.';
            $parts['complement'] = count(array_intersect($candidate['patterns'], $complements)) ? $rules['weights']['complement'] : 0;
            if ($parts['complement']) $reasons[] = 'Complementa el patrón del ejercicio anterior.';
            $newMuscles = array_diff($candidate['primary_muscles'], array_keys($muscles));
            $parts['coverage'] = count($newMuscles) * $rules['weights']['new_primary_muscle'];
            if ($newMuscles) $reasons[] = 'Añade trabajo para grupos musculares principales todavía no cubiertos.';
            $parts['preference'] = in_array($candidate['id'], $preferredIds, true) ? $rules['weights']['preferred'] : 0;
            if ($parts['preference']) $reasons[] = 'Coincide con una preferencia del atleta.';
            $parts['variant'] = isset($families[$candidate['family']]) ? $rules['weights']['same_variant_family'] : 0;
            if ($parts['variant']) $reasons[] = 'Comparte familia con una variante ya seleccionada.';
            $cost = $rules['fatigue_cost'][$candidate['fatigue']];
            $parts['fatigue'] = $cost * $rules['weights']['fatigue_cost'];
            $parts['fatigue_budget'] = max(0, $fatigue + $cost - $rules['fatigue_soft_budget']) * $rules['weights']['over_fatigue'];
            if ($parts['fatigue_budget']) $reasons[] = 'Supera el presupuesto orientativo de fatiga de esta propuesta.';
            $over = 0;
            foreach ($candidate['primary_muscles'] as $muscle) if (($muscles[$muscle] ?? 0) + $rules['sets_per_exercise'] > $rules['muscle_series_soft_budget']) $over++;
            $parts['volume'] = $over * $rules['weights']['over_muscle_budget'];
            if ($over) $reasons[] = 'Añade series a músculos con volumen propuesto ya elevado.';
            $ranked[] = ['id' => $candidate['id'], 'code' => $candidate['code'], 'names' => $candidate['names'],
                'score' => array_sum($parts), 'score_breakdown' => $parts, 'reasons' => $reasons,
                'patterns' => $candidate['patterns'], 'primary_muscles' => $candidate['primary_muscles'],
                'review_status' => $candidate['review_status']];
        }
        usort($ranked, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['code'], $b['code']));
        return $ranked;
    }
}
