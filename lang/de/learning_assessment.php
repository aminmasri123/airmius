<?php

return json_decode(file_get_contents(resource_path('data/learning-assessment-copy.json')), true, flags: JSON_THROW_ON_ERROR)['de'];
