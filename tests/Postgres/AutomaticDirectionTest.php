<?php

namespace Tests\Postgres;

use Carbon\CarbonImmutable;
use Tests\AutomaticDirectionWorkflow;
use Tests\PostgresTestCase;

class AutomaticDirectionTest extends PostgresTestCase
{
    use AutomaticDirectionWorkflow;

    protected function directionFixture(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10T00:00:00Z'));

        return $this->fixture();
    }
}
