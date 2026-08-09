<?php

return [
    'evidence_path' => env(
        'AIRMIUS_GOVERNANCE_EVIDENCE_PATH',
        base_path('resources/release/governance_evidence.local.json'),
    ),
    'platform_gate_path' => base_path('resources/release/platform_release_gates.json'),
];
