<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $compiledViewsPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'airmius-test-views-'.getmypid();

        if (! is_dir($compiledViewsPath)) {
            mkdir($compiledViewsPath, 0777, true);
        }

        config(['view.compiled' => $compiledViewsPath]);
    }
}
