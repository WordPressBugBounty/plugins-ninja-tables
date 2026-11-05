<?php

if (!defined('ABSPATH')) {
    die(__FILE__);
}

return [
    "pricing"    => [
        "name"   => __("Pricing Tables", "ninja-tables"),
        "key"    => "pricing", // unique,
        "tables" => [
            [
                "name"      => __("Pricing Table One", "ninja-tables"),
                "key"       => "pricing_table_one", // unique
                "image_url" => '#',
                "has_pro"   => false,
            ],
            [
                "name"      => __("Pricing Table Two", "ninja-tables"),
                "key"       => "pricing_table_two", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ],
            [
                "name"      => __("Pricing Table Three", "ninja-tables"),
                "key"       => "pricing_table_three", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ],
            [
                "name"      => __("Pricing Table Four", "ninja-tables"),
                "key"       => "pricing_table_four", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ],
        ],
    ],
    "comparison" => [
        "name"   => __("Comparison Tables", "ninja-tables"),
        "key"    => "comparison", // unique,
        "tables" => [
            [
                "name"      => __("Comparison Table One", "ninja-tables"),
                "key"       => "comparison_table_one", // unique
                "image_url" => '#',
                "has_pro"   => false,
            ],
            [
                "name"      => __("Comparison Table Two", "ninja-tables"),
                "key"       => "comparison_table_two", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ],
            [
                "name"      => __("Comparison Table Three", "ninja-tables"),
                "key"       => "comparison_table_three", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ],
            [
                "name"      => __("Comparison Table Four", "ninja-tables"),
                "key"       => "comparison_table_four", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ],
            [
                "name"      => __("Comparison Table Five", "ninja-tables"),
                "key"       => "comparison_table_five", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ],

        ],
    ],
    "employee"   => [
        "name"   => __("Employee Tables", "ninja-tables"),
        "key"    => "employee", // unique,
        "tables" => [
            [
                "name"      => __("Employee Table One", "ninja-tables"),
                "key"       => "employee_table_one", // unique
                "image_url" => '#',
                "has_pro"   => false,
            ],
            [
                "name"      => __("Employee Table Two", "ninja-tables"),
                "key"       => "employee_table_two", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ],
            [
                "name"      => __("Employee Table Three", "ninja-tables"),
                "key"       => "employee_table_three", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ]

        ],
    ],
    "schedule"   => [
        "name"   => __("Schedule Tables", "ninja-tables"),
        "key"    => "schedule", // unique,
        "tables" => [
            [
                "name"      => __("Schedule Table One", "ninja-tables"),
                "key"       => "schedule_table_one", // unique
                "image_url" => '#',
                "has_pro"   => false,
            ],
            [
                "name"      => __("Schedule Table Two", "ninja-tables"),
                "key"       => "schedule_table_two", // unique
                "image_url" => '#',
                "has_pro"   => false,
            ],
            [
                "name"      => __("Schedule Table Three", "ninja-tables"),
                "key"       => "schedule_table_three", // unique
                "image_url" => '#',
                "has_pro"   => false,
            ],
            [
                "name"      => __("Opening Hours", "ninja-tables"),
                "key"       => "opening_hours", // unique
                "image_url" => '#',
                "has_pro"   => false,
            ]

        ],
    ],
    // Categories below are served from config/table-builder/templates/<key>.json
    // rather than a method on ReadyMadeTable.
    "specification" => [
        "name"   => __("Specification Tables", "ninja-tables"),
        "key"    => "specification", // unique,
        "tables" => [
            [
                "name"      => __("Spec Sheet", "ninja-tables"),
                "key"       => "spec_sheet", // unique
                "image_url" => '#',
                "has_pro"   => false,
            ],
            [
                "name"      => __("Product Specs", "ninja-tables"),
                "key"       => "product_specs", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ]
        ],
    ],
    "stats"         => [
        "name"   => __("Stats Tables", "ninja-tables"),
        "key"    => "stats", // unique,
        "tables" => [
            [
                "name"      => __("Leaderboard", "ninja-tables"),
                "key"       => "leaderboard", // unique
                "image_url" => '#',
                "has_pro"   => false,
            ],
            [
                "name"      => __("Stats Dashboard", "ninja-tables"),
                "key"       => "stats_dashboard", // unique
                "image_url" => '#',
                "has_pro"   => true,
            ]
        ],
    ],
];
