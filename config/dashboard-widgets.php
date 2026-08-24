<?php

/** Widget registry Dashboard Builder (ADR-063). Hanya widget approved di sini. */
return [
    'revenue' => ['label' => 'Revenue YTD', 'permission' => 'finance.view', 'roles' => ['Director', 'Finance Manager'], 'width' => 3],
    'ar_ap' => ['label' => 'AR / AP Outstanding', 'permission' => 'finance.view', 'roles' => ['Director', 'Finance Manager'], 'width' => 3],
    'tender_pipeline' => ['label' => 'Tender Pipeline', 'permission' => 'tender.view', 'roles' => ['Director'], 'width' => 3],
    'project_health' => ['label' => 'Project Health', 'permission' => 'project.view', 'roles' => null, 'width' => 6],
    'bored_pile_progress' => ['label' => 'Bored Pile Progress', 'permission' => 'project.view', 'roles' => null, 'width' => 3],
    'procurement_delay' => ['label' => 'Procurement Delay', 'permission' => 'procurement.view', 'roles' => null, 'width' => 3],
    'inventory_critical' => ['label' => 'Inventory Critical', 'permission' => 'inventory.view', 'roles' => null, 'width' => 3],
    'ncr_open' => ['label' => 'NCR Terbuka', 'permission' => 'qms.view', 'roles' => null, 'width' => 3],
    'hse_incident' => ['label' => 'Incident HSE', 'permission' => 'hse.view', 'roles' => null, 'width' => 3],
    'my_work' => ['label' => 'My Work', 'permission' => null, 'roles' => null, 'width' => 3],
];
