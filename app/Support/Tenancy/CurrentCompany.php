<?php

namespace App\Support\Tenancy;

use App\Models\Company;

class CurrentCompany
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
    }

    public function get(): Company
    {
        return $this->company ?? throw new \RuntimeException('Konteks perusahaan belum dipilih.');
    }

    public function id(): int
    {
        return $this->get()->id;
    }
}
