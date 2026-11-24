<?php

return [
    'max_kilobytes' => (int) env('EMPLOYEE_IMPORT_MAX_KB', 2048),
    'max_rows' => (int) env('EMPLOYEE_IMPORT_MAX_ROWS', 1000),
    'preview_rows' => 50,
    'expires_minutes' => 60,
];
