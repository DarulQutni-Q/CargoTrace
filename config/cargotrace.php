<?php

// CargoTrace group-scenario configuration [S8, S20].
// The delay thresholds follow the brief's example until the lecturer's
// group configuration replaces them via .env — never edit code to change
// a scenario (see GROUP_SCENARIO.md).

return [

    'delay' => [
        'low_threshold_hours' => (int) env('CARGOTRACE_DELAY_LOW', 6),
        'high_threshold_hours' => (int) env('CARGOTRACE_DELAY_HIGH', 24),
    ],

    'scenario' => [
        'route' => env('CARGOTRACE_ROUTE'),
        'primary_exception' => env('CARGOTRACE_PRIMARY_EXCEPTION'),
        'kpi' => env('CARGOTRACE_KPI'),
    ],

    // External geocoding (OpenStreetMap Nominatim). No API key is needed,
    // but a proper user agent is mandatory per their usage policy; keys
    // for other providers belong in .env, never in code [S9].
    'geocoding' => [
        'url' => env('CARGOTRACE_GEOCODING_URL', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env('CARGOTRACE_GEOCODING_USER_AGENT', 'CargoTrace/1.0 (UAS logistics project)'),
        'timeout' => (int) env('CARGOTRACE_GEOCODING_TIMEOUT', 5),
    ],

];
