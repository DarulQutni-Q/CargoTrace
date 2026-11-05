<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CargoTrace Corridor & Deterministic Delay Rule Configuration
    |--------------------------------------------------------------------------
    |
    | Supports DEFAULT, GROUP_A, GROUP_B, GROUP_C, and CUSTOM thresholds.
    | Color Palette: #080B0C (Obsidian), #FFFFFF (Pure White - Default), #C6F432 (Acid Volt)
    |
    */
    'default_theme' => env('CARGOTRACE_DEFAULT_THEME', 'light'),

    'palette' => [
        'obsidian' => '#080B0C',
        'white' => '#FFFFFF',
        'volt' => '#C6F432',
    ],

    'scenario' => [
        'group_code' => env('CARGOTRACE_GROUP_CODE', 'DEFAULT'),
        'route_corridor' => env('CARGOTRACE_CORRIDOR', 'Southeast Asia → Europe'),
        'low_threshold_hours' => (int) env('CARGOTRACE_DELAY_LOW_HOURS', 6),
        'high_threshold_hours' => (int) env('CARGOTRACE_DELAY_HIGH_HOURS', 24),
        'primary_exception' => env('CARGOTRACE_PRIMARY_EXCEPTION', 'CUSTOMS_HOLD'),
        'kpi_focus' => env('CARGOTRACE_KPI_FOCUS', 'On-Time Delivery & Exception Rate'),
    ],

    'presets' => [
        'DEFAULT' => [
            'group_code' => 'DEFAULT',
            'route_corridor' => 'Southeast Asia → Europe',
            'low_threshold_hours' => 6,
            'high_threshold_hours' => 24,
            'primary_exception' => 'CUSTOMS_HOLD',
            'kpi_focus' => 'On-Time Delivery & Exception Rate',
        ],
        'GROUP_A' => [
            'group_code' => 'GROUP_A',
            'route_corridor' => 'Surabaya → Singapore → Rotterdam',
            'low_threshold_hours' => 12,
            'high_threshold_hours' => 24,
            'primary_exception' => 'PORT_CONGESTION',
            'kpi_focus' => 'Average Delay Duration (hours)',
        ],
        'GROUP_B' => [
            'group_code' => 'GROUP_B',
            'route_corridor' => 'Jakarta → Colombo → Hamburg',
            'low_threshold_hours' => 18,
            'high_threshold_hours' => 36,
            'primary_exception' => 'CUSTOMS_HOLD',
            'kpi_focus' => 'On-Time Delivery Rate (%)',
        ],
        'GROUP_C' => [
            'group_code' => 'GROUP_C',
            'route_corridor' => 'Bangkok → Dubai → Antwerp',
            'low_threshold_hours' => 24,
            'high_threshold_hours' => 48,
            'primary_exception' => 'WEATHER_DELAY',
            'kpi_focus' => 'Shipments with Open Exceptions per Carrier',
        ],
    ],

    'external_apis' => [
        'open_meteo_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1/forecast'),
        'nominatim_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org/search'),
        'timeout_seconds' => 5,
    ],
];
