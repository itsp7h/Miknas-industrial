<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purchase pipeline permissions
    |--------------------------------------------------------------------------
    |
    | Each key is the exact Spatie permission name; the value is the
    | human-readable label shown as a toggle in the User Management page.
    |
    */
    'permissions' => [
        'purchase-requests.create'              => 'Create purchase requests',
        'purchase-requests.edit'                => 'Edit purchase requests',
        'purchase-requests.view-own'            => 'View own purchase requests',
        'purchase-requests.view-active-pipeline' => 'View active pipeline (RFQ onward)',
        'purchase-requests.view-all'            => 'View all purchase requests (monitoring)',
        'purchase-requests.approve'             => 'Approve/reject purchase requests (GM signature)',
        'purchase-requests.manage-rfq'          => 'Select suppliers and send RFQ',
        'purchase-requests.manage-quotes'       => 'View and manage supplier quotes',
        'purchase-requests.award'               => 'Award items to suppliers',
        'purchase-requests.generate-lpo'        => 'Generate LPO',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-built profiles
    |--------------------------------------------------------------------------
    |
    | Each profile is a Spatie role bundling a fixed set of the permissions
    | above. Assigning a profile to a person grants these as a starting
    | point; an Admin can still toggle individual permissions per person on
    | top of (or instead of) any profile via the User Management page.
    |
    */
    'profiles' => [
        'Requester' => [
            'purchase-requests.create',
            'purchase-requests.edit',
            'purchase-requests.view-own',
        ],
        'Purchase Manager' => [
            'purchase-requests.approve',
            'purchase-requests.view-all',
        ],
        'Procurement Officer' => [
            'purchase-requests.manage-rfq',
            'purchase-requests.manage-quotes',
            'purchase-requests.award',
            'purchase-requests.generate-lpo',
            'purchase-requests.view-active-pipeline',
        ],
    ],

];
