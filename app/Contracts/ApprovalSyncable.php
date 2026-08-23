<?php

namespace App\Contracts;

interface ApprovalSyncable
{
    public function syncApprovalStatus(string $decision): void;
}
