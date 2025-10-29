<?php
namespace AloxStore\CPT\Products;

if (!defined('ABSPATH')) exit;

/**
 * Loader / facade for the Product CPT subsystem.
 *
 * Responsibility:
 *  - Require component classes.
 *  - Ensure side-effect init() methods are executed.
 *
 * This file is intended to be required_once by the main plugin bootstrap.
 */

// individual modules are in same directory
require_once __DIR__ . '/Register.php';
require_once __DIR__ . '/Meta.php';
require_once __DIR__ . '/MetaBox.php';
require_once __DIR__ . '/AdminColumns.php';

// Nothing else to run here because each class self-inits via ->init() above.
