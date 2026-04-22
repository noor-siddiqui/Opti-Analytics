<?php
declare(strict_types=1);

namespace OptiAnalytics;

// Security Best Practice: Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The core plugin class.
 * * This class is used to define internationalization, admin-specific hooks,
 * and public-facing site hooks.
 */
class Core
{

    /**
     * Constructor for the core class.
     */
    public function __construct()
    {
        // We will initialize internal properties here later.
    }

    /**
     * Execute the main logic of the plugin.
     * This is where we will register all of our WordPress hooks (actions and filters).
     */
    public function run(): void
    {
        // Placeholder: Register WooCommerce admin menu hook here in the next step.
    }
}