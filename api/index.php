<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
http_response_code(501);
echo json_encode([
    'ok' => false,
    'message' => 'API bootstrap will be completed in Phase 7.',
], JSON_THROW_ON_ERROR);
