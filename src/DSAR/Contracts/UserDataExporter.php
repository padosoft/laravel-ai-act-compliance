<?php

namespace Padosoft\AiActCompliance\DSAR\Contracts;

interface UserDataExporter
{
    public function export(object $user): array;
}
