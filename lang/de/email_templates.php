<?php

use App\Support\EmailTemplate;

return array_map(
    static fn (array $definition): array => $definition['template'],
    EmailTemplate::definitions(),
);
