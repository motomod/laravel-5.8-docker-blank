<?php

namespace JKocik\Laravel\Profiler\Contracts;

interface DataProcessor
{
    /**
     * @param DataTracker $dataTracker
     * @return void
     */
    public function process(DataTracker $dataTracker): void;
}
