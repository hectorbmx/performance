<?php
return [
    'version' => 'barbell-epley-rir-v1',
    'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'target_rir' => 2, 'rest_seconds' => 90,
    'max_rm_age_days' => 90, 'max_history_age_days' => 45,
    'estimate_max_effective_reps' => 10, 'estimate_max_rir' => 3,
    'initial_max_effective_reps' => 15,
    'increments' => ['0.25', '0.50', '1.00', '1.25', '2.50', '5.00'],
    'increase_requires_sets' => 2, 'reduction_pct' => 5,
];
