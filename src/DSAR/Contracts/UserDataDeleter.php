<?php

namespace Padosoft\AiActCompliance\DSAR\Contracts;

interface UserDataDeleter
{
    public function delete(object $user): void;
}
