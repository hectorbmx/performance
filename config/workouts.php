<?php

return [
    // Enable only after applying the additive migration and validating the pilot.
    // Disabling new starts never disables authorized history or start retries.
    'generator_enabled' => env('WORKOUT_GENERATOR_ENABLED', false),
    'executions_enabled' => env('WORKOUT_EXECUTIONS_ENABLED', false),
];
