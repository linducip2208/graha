<?php

/** Edition Builder (ADR-065): paket modul per edition. Null modules = semua. */
return [
    'foundation-erp' => ['label' => 'Foundation ERP', 'modules' => null],
    'construction-erp' => ['label' => 'Construction ERP', 'modules' => ['manufacturing', 'accounting', 'other']],
    'manufacturing-edition' => ['label' => 'Manufacturing Edition', 'modules' => ['manufacturing', 'accounting']],
    'equipment-edition' => ['label' => 'Equipment Edition', 'modules' => ['other']],
    'project-finance-edition' => ['label' => 'Project Finance Edition', 'modules' => ['accounting', 'other']],
];
