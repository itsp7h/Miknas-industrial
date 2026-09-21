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
        ],
        'finished-goods' => [
            'group' => 'Inventory',
            'label' => 'Finished Goods',
            'actions' => ['view', 'create', 'edit', 'delete'],
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
                'purchase-orders.view',
                'goods-receipts.view',
                'supplier-invoices.view',
                'supplier-payments.view',
                'raw-materials.view',
                'finished-goods.view',
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
                'finished-goods.view',
                'valuation.view',
                'finance.view',
                'finance.edit',
            ],
        ],
    ],

];
