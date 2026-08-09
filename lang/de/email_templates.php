<?php

return array_map(
    static fn (array $definition): array => $definition['template'],
    \App\Support\EmailTemplate::definitions(),
);
