<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('wmtoc_settings');
delete_site_transient('wmtoc_remote');
