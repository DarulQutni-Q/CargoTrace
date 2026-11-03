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

];
