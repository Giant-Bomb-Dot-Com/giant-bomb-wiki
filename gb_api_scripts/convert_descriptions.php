<?php

require_once __DIR__ . "/convert_descriptions_base.php";

class ConvertToMWDescriptions extends ConvertToMWDescriptionsBase
{
    protected function forceFullTableRescan(): bool
    {
        return false;
    }
}

$maintClass = ConvertToMWDescriptions::class;

require_once RUN_MAINTENANCE_IF_MAIN;
