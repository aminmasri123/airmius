<?php

return json_decode(file_get_contents(resource_path('data/ride-pricing-copy.json')), true, flags: JSON_THROW_ON_ERROR)['de'];
