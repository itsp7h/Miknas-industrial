<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    |
    | One entry per sidebar tab. `actions` is what that tab can actually be
    | granted — a report has nothing to create, and a stock movement is a ledger
    | line that is never edited or deleted, only posted.
    |
    | Each produces a permission named `<key>.<action>`, so the Users page can
    | show a grid and an Admin can hand out any square of it to anyone,
    | whatever profile they hold.
    |
    | `extra` is for the things CRUD cannot express — approving a request,
    | awarding a quote. They hang off the tab they belong to.
    |
    */
    'tabs' => [

        // ── Purchase ────────────────────────────────────────────────────────
        'pipeline' => [
            'group' => 'Purchase',
            'label' => 'Pipeline',
            'actions' => ['view', 'create', 'edit', 'delete'],
            // `view` opens the tab. The three scope entries below decide how
            // much of it is in there.
            'extra' => [
                'approve' => 'Approve / reject (GM signature)',
                'manage-rfq' => 'Select suppliers and send RFQ',
                'manage-quotes' => 'View and manage supplier quotes',
                'award' => 'Award items to suppliers',
                'generate-lpo' => 'Generate LPO',
                'view-own' => 'See only their own requests',
                'view-all' => 'See every request, including drafts',
                'view-active-pipeline' => 'See requests from RFQ onward',
            ],
        ],
        'suppliers' => [
            'group' => 'Purchase',
            'label' => 'Suppliers',
            'actions' => ['view', 'create', 'edit', 'delete'],
            // Moving a spreadsheet in or a list out is not the same capability
            // as adding one supplier by hand, so it is its own square rather
            // than something `create` and `view` quietly carry.
            'extra' => [
                'import' => 'Import from Excel, and download the template',
                'export' => 'Export the list as PDF',
                // Emptying the directory in one action is not the same as
                // deleting one supplier you are looking at, so it is granted
                // separately and `delete` does not imply it.
                'delete-all' => 'Delete every supplier at once',
            ],
        ],
        'purchase-orders' => [
            'group' => 'Purchase',
            'label' => 'Purchase Orders',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'goods-receipts' => [
            'group' => 'Purchase',
            'label' => 'Goods Receipt (GRN)',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'supplier-invoices' => [
            'group' => 'Purchase',
            'label' => 'Supplier Invoices',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'supplier-payments' => [
            'group' => 'Purchase',
            'label' => 'Payments',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],

        // ── Inventory ───────────────────────────────────────────────────────
        'raw-materials' => [
            'group' => 'Inventory',
            'label' => 'Raw Materials',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'extra' => [
                'import' => 'Import from Excel, and download the template',
                'export' => 'Export the list as PDF',
            ],
        ],
        'finished-goods' => [
            'group' => 'Inventory',
            'label' => 'Finished Goods',
            'actions' => ['view', 'create', 'edit', 'delete'],
            'extra' => [
                'import' => 'Import from Excel, and download the template',
                'export' => 'Export the list as PDF',
            ],
        ],
        'warehouses' => [
            'group' => 'Inventory',
            'label' => 'Warehouses',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'stock-movements' => [
            'group' => 'Inventory',
            'label' => 'Stock Movements',
            // A movement is a ledger line. It is posted, never rewritten — the
            // correction for a wrong one is an opposing movement.
            'actions' => ['view', 'create'],
        ],
        'movement-report' => [
            'group' => 'Inventory',
            'label' => 'Movement Report',
            'actions' => ['view'],
        ],
        'low-stock' => [
            'group' => 'Inventory',
            'label' => 'Low Stock Alert',
            'actions' => ['view'],
        ],
        'valuation' => [
            'group' => 'Inventory',
            'label' => 'Valuation',
            'actions' => ['view'],
        ],

        // ── System ──────────────────────────────────────────────────────────
        'companies' => [
            'group' => 'System',
            'label' => 'Companies',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'projects' => [
            'group' => 'System',
            'label' => 'Projects',
            'actions' => ['view', 'create', 'edit', 'delete'],
            // No export square: projects have an import and a template, but
            // nothing that writes a PDF.
            'extra' => [
                'import' => 'Import from Excel, and download the template',
            ],
        ],
        'item-categories' => [
            'group' => 'System',
            'label' => 'Item Categories',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'finance' => [
            'group' => 'System',
            'label' => 'Finance',
            'actions' => ['view', 'edit'],
        ],
        // Empty for now — the page is a placeholder until we know what belongs
        // on it. It is a tab from the start so access to it is grantable the
        // day it has something on it, rather than being bolted on after.
        'settings' => [
            'group' => 'System',
            'label' => 'Settings',
            'actions' => ['view', 'edit'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin-only tabs
    |--------------------------------------------------------------------------
    |
    | Deliberately not in the list above: they have no permission name, so they
    | cannot be granted to anyone. Admin is the only way in.
    |
    */
    'admin_only_tabs' => ['users', 'integrations'],

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */
    'action_labels' => [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    |
    | A starting point, not a cage. Assigning one grants the squares listed
    | here; an Admin can then add or remove any square for that person.
    |
    | Admin grants nothing explicitly — `Gate::before` passes it through every
    | ability, and listing them would invite one being removed.
    |
    */
    'profiles' => [
        'Admin' => [
            'description' => 'Everything, and the only profile that can manage users and integrations.',
            'permissions' => [],
        ],
        'Operation Manager' => [
            'description' => 'Raises purchase requests.',
            'permissions' => [
                'pipeline.view',
                'pipeline.create',
                'pipeline.view-own',
            ],
        ],
        'GM' => [
            'description' => 'Sees everything and signs off purchase requests.',
            'permissions' => [
                'pipeline.view',
                'pipeline.view-all',
                'pipeline.approve',
                'suppliers.view',
                'suppliers.export',
                'purchase-orders.view',
                'goods-receipts.view',
                'supplier-invoices.view',
                'supplier-payments.view',
                'raw-materials.view',
                'raw-materials.export',
                'finished-goods.view',
                'finished-goods.export',
                'warehouses.view',
                'stock-movements.view',
                'movement-report.view',
                'low-stock.view',
                'valuation.view',
            ],
        ],
        'Finance' => [
            'description' => 'Handles supplier invoices and payments; sees the rest read-only.',
            'permissions' => [
                'pipeline.view',
                'pipeline.view-all',
                'suppliers.view',
                'suppliers.export',
                'purchase-orders.view',
                'goods-receipts.view',
                'supplier-invoices.view',
                'supplier-invoices.create',
                'supplier-invoices.edit',
                'supplier-invoices.delete',
                'supplier-payments.view',
                'supplier-payments.create',
                'supplier-payments.edit',
                'supplier-payments.delete',
                'raw-materials.view',
                'raw-materials.export',
                'finished-goods.view',
                'finished-goods.export',
                'valuation.view',
                'finance.view',
                'finance.edit',
            ],
        ],
    ],

];
