<?php
// Product heuristics for ordering, not universal physiological thresholds.
// Change version with every behavior change; validate with a coach before pilot.
return [
    'version' => 'upper-lower-hypertrophy-intermediate-v2',
    'profile' => ['region' => 'lower_body', 'goal' => 'hypertrophy', 'level' => 'intermediate'],
    'sets_per_exercise' => 3,
    'muscle_series_soft_budget' => 12,
    'fatigue_soft_budget' => 8,
    'fatigue_cost' => ['low' => 1, 'moderate' => 2, 'high' => 3],
    'weights' => ['new_pattern' => 40, 'repeated_pattern' => -20, 'complement' => 60,
        'new_primary_muscle' => 20, 'preferred' => 10, 'same_variant_family' => -25,
        'fatigue_cost' => -5, 'over_fatigue' => -10, 'over_muscle_budget' => -10],
    'complements' => ['horizontal_push'=>['horizontal_pull'], 'horizontal_pull'=>['horizontal_push'], 'vertical_push'=>['vertical_pull'], 'vertical_pull'=>['vertical_push'], 'elbow_flexion'=>['elbow_extension'], 'elbow_extension'=>['elbow_flexion'], 'hip_hinge' => ['knee_dominant'], 'knee_dominant' => ['hip_hinge']],
];
