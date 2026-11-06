<?php

// Spanish issues for the "details" of VALIDATION_ERROR (user-facing text stays in Spanish).
return [
    'required' => 'obligatorio',
    'string' => 'debe ser texto',
    'integer' => 'debe ser un número entero',
    'array' => 'debe ser una lista',
    'uuid' => 'debe ser un UUID',
    'date' => 'debe ser una fecha válida',
    'date_format' => 'debe tener el formato :format',
    'in' => 'valor no permitido',
    'regex' => 'formato inválido',
    'file' => 'debe ser un archivo',
    'distinct' => 'valor repetido',
    'max' => [
        'string' => 'máximo :max caracteres',
        'numeric' => 'máximo :max',
        'array' => 'máximo :max elementos',
        'file' => 'máximo :max KB',
    ],
    'min' => [
        'string' => 'mínimo :min caracteres',
        'numeric' => 'mínimo :min',
        'array' => 'mínimo :min elementos',
        'file' => 'mínimo :min KB',
    ],
    'between' => [
        'numeric' => 'debe estar entre :min y :max',
    ],
    'size' => [
        'string' => 'debe tener :size caracteres',
    ],
    'attributes' => [],
];
