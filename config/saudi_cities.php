<?php

/**
 * Canonical city keys for Saudi address comboboxes. English labels in lang/en/saudi_cities.php
 * are persisted to the database (ZATCA CityName).
 */
return [
    'keys' => [
        'riyadh',
        'jeddah',
        'mecca',
        'medina',
        'dammam',
        'khobar',
        'dhahran',
        'jubail',
        'taif',
        'tabuk',
        'buraidah',
        'unaizah',
        'khamis_mushait',
        'abha',
        'hail',
        'najran',
        'jazan',
        'yanbu',
        'al_ahsa',
        'qatif',
        'al_kharj',
        'sakaka',
        'arar',
        'al_bahah',
        'hafar_al_batin',
        'diriyah',
        'al_majmaah',
        'al_qunfudhah',
        'bisha',
        'rabigh',
    ],
    'other_key' => 'other',
    /** Alternate spellings → canonical English storage name (lang en value). */
    'aliases' => [
        'hofuf' => 'al_ahsa',
        'al hofuf' => 'al_ahsa',
        'al-hofuf' => 'al_ahsa',
        'makkah' => 'mecca',
        'madinah' => 'medina',
        'al khobar' => 'khobar',
        'alkhobar' => 'khobar',
        'dammam metropolitan' => 'dammam',
    ],
];