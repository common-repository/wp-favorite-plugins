<?php
/**
 * Plugin Name: WP Favorite Plugins
 * Plugin URI: http://nextpress.co/
 * Description: Simply add a new Favorite table to your plugins page, to have easy access to all your favorite and most used plugins
 * Version: 0.0.1
 * Author: Arindo Duque - NextPress
 * Author URI: http://nextpress.co/
 * Copyright: Arindo Duque, NextPress
 */

if (!class_exists('WP_Favorite_Plugins')) :

class WP_Favorite_Plugins {

  /**
   * Slug of the option that holdes the favorite plugins
   * @var string
   */
  public $option_slug = 'wpfp_list';

  /**
   * Control the filtering to be run just once
   * @var boolean
   */
  public $runned = 0;

  /**
   * Initializes the plugin
   */
  public function __construct() {

    // Get the language
    load_theme_textdomain('wpfp', plugin_dir_url(__FILE__).'/lang');

    // We need to add the add to fav icon on the plugins list
    add_filter('plugin_action_links', array($this, 'add_fav_icon'), 10, 4);
    
    // Enqueue Scripts
    add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

    // Save the plugins favorite list
    add_action('current_screen', array($this, 'save_list'));
    
    // Displays the new favorite list, replicating the list bellow in the plugins page
    add_action('pre_current_active_plugins', array($this, 'display_favorite_table'));

    // Load bulk actions
    add_action('load-plugins.php', array($this, 'proccess_bulk_actions'));

    // Notices
    add_action('admin_notices', array($this, 'add_bulk_actions_notices'));

    // Add the option to the bulk action
    // add_filter('bulk_actions-plugins', array($this, 'add_bulk_actions'));

  } // end construct;

  /**
   * Add the bulk actions buttons
   * @param array $actions Actions available on the select dropdown
   */
  public function add_bulk_actions($actions) {
    $actions['favorite-selected']   = __('Favorite', 'wpfp');
    $actions['unfavorite-selected'] = __('Unfavorite', 'wpfp');
    // var_dump($actions);
    return $actions;
  }

  /**
   * Add the notices after a successful save
   */
  function add_bulk_actions_notices() {
    global $post_type, $pagenow;
   
    if ($pagenow == 'plugins.php' && isset($_REQUEST['favorited']) && (int) $_REQUEST['favorited']) {
      $message = sprintf(_n('Plugin favorited.', '%s plugins favorited.', $_REQUEST['favorited']), number_format_i18n($_REQUEST['favorited']));
      echo "<div class='notice notice-success is-dismissible'><p>{$message}</p></div>";
    }

    if ($pagenow == 'plugins.php' && isset($_REQUEST['unfavorited']) && (int) $_REQUEST['unfavorited']) {
      $message = sprintf(_n('Plugin unfavorited.', '%s plugins unfavorited.', $_REQUEST['unfavorited']), number_format_i18n($_REQUEST['unfavorited']));
      echo "<div class='notice notice-success is-dismissible'><p>{$message}</p></div>";
    }
  }

  /**
   * Process the bulk actions
   */
  function proccess_bulk_actions() {

    // 1. get the action
    $wp_list_table = _get_list_table('WP_Plugins_List_Table');
    $action = $wp_list_table->current_action();

    if (!$action) return;
    if ($action !== 'favorite' && $action !== 'unfavorite') return;
   
    // List of plugins to update
    $plugins = isset($_POST['checked']) ? $_POST['checked'] : false;
    
    if (!$plugins) return;

    // 2. security check
    check_admin_referer('bulk-plugins');

    // Make sendback
    $sendback = admin_url('plugins.php');

    switch($action) {

      /**
       * In the case of bulk favorite
       */
      case 'favorite':

        // Get the list of plugins
        $list = $this->get_list();

        // Loop them
        foreach($plugins as $plugin) {
          $list = $this->add_plugin_to_favorites($plugin, $list);
        }
        
        // build the redirect url
        $sendback = add_query_arg(array('favorited' => count($plugins)), $sendback);
   
      break;

      /**
       * In the case of bulk unfavorite
       */
      case 'unfavorite':

        // Get the list of plugins
        $list = $this->get_list();

        // Loop them
        foreach($plugins as $plugin) {
          $list = $this->remove_plugin_from_favorites($plugin, $list);
        }
        
        // build the redirect url
        $sendback = add_query_arg(array('unfavorited' => count($plugins)), $sendback);
   
      break;

    } // end switch;
   
    // Update Option
    update_option($this->option_slug, $list);
   
    // 4. Redirect client
    wp_redirect($sendback); exit();

  } // end proccess_bulk_actions;

  /**
   * Filter the plugin list to our favorite plugins only
   * @param  array $plugins Array of all plugins disponible
   * @return array          Return the filtered filters array
   */
  public function filter_plugins($plugins) {

    // var_dump($this->runned++);

    // Check if already runned
    if ($this->runned == 1) return $plugins;

    // Filter on one
    else if ($this->runned == 0) {

      foreach($plugins as $plugin_file => &$plugin) {
        if (!in_array($plugin_file, $this->get_list())) 
          unset($plugins[$plugin_file]);
      }

    } // end else if;

    // Set the runned flag
    $this->runned = $this->runned++;

    return $plugins;

  } // end filter_plugins;

  /**
   * Enqueue the needed scripts and localized
   */
  public function enqueue_scripts() {

    // Security checks
    if (get_current_screen()->id != 'plugins') return;

    // Enqueue Script
    wp_register_script('wpfp', plugin_dir_url(__FILE__).'/wpfp.js', array('jquery'));

    // Add localization strings
    wp_localize_script('wpfp', 'wpfp', array(
      'favorite'   => __('Favorite', 'wpfp'),
      'unfavorite' => __('Unfavorite', 'wpfp'),
    ));

    // Enqueue Script
    wp_enqueue_script('wpfp');

  } // end enqueue_scripts;

  /**
   * Get the favorite plugins list
   * @return array List of favorite plugins
   */
  public function get_list() {
    return get_option($this->option_slug, array());
  }

  /**
   * Save list of favorites plugin
   */
  public function save_list() {

    $screen = get_current_screen();

    // Security checks
    if ($screen->id != 'plugins') return;
    if (!isset($_GET['action']) || !isset($_GET['plugin']) || !isset($_GET['_wpnonce'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'], 'wpfp')) return;

    // Save the list
    $list = $this->get_list();

    // Switch action
    switch($_GET['action']) {

      case 'favorite':
        // add the new plugin to the list
        $list = $this->add_plugin_to_favorites($_GET['plugin'], $list);
        break;

      case 'unfavorite':
        $list = $this->remove_plugin_from_favorites($_GET['plugin'], $list);
        break;

    } // end switch;

    // Update Option
    update_option($this->option_slug, $list);
    // var_dump($list);

  } // end save_list;

  /**
   * Add the link to the actions
   * @param string $plugin_file Plugins file name to add
   * @param array  $list        List of favorite plugins
   */
  public function add_plugin_to_favorites($plugin_file, $list) {
    $list[] = $plugin_file;
    $list = array_unique($list);
    return $list;
  } // end add_plugin_to_favorite;

  /**
   * Remove a plugin from the favorite plugins
   * @param  string $plugin_file Plugin file name to remove
   * @param  array  $list        Array of favorite plugins
   * @return array               Filtered list after removal
   */
  public function remove_plugin_from_favorites($plugin_file, $list) {
    if (($key = array_search($plugin_file, $list)) !== false) {
      unset($list[$key]);
    }
    return $list;
  }

  /**
   * Add action link to the actions row of plugins table
   * @param array  $actions     Actions passed by WordPress
   * @param atring $plugin_file Plugin file name
   * @param array  $plugin_data Info data
   */
  public function add_fav_icon($actions, $plugin_file, $plugin_data, $context) {

    // Check if this plugin is on the list
    $fav    = in_array($plugin_file, $this->get_list());
    $type   = $fav ? 'unfavorite' : 'favorite';
    $string = $fav ? __('Remove from the Favorite List', 'wpfp') : __('Add to the Favorite List', 'wpfp');

    // URL of the plugin
    $url = wp_nonce_url(admin_url('plugins.php?action='. $type .'&plugin='.$plugin_file), 'wpfp');

    // Create our custom action
    $action = sprintf('<a class="wpfp-add-plugin" href="%s">%s</a>', $url, $string);

    // Add the action
    $actions['fav'] = $action;
    return $actions;

  } // end add_fav_icon;

  /**
   * Displays the list of favorite plugins
   * @param  array $plugins List of all active plugins
   */
  public function display_favorite_table($plugins) {

    global $totals;

    // Save the right totals
    $this->totals = $totals;

    // Apply filters on the first list table
    add_filter('all_plugins', array($this, 'filter_plugins'));

    $fav_table = $wp_list_table = _get_list_table('WP_Plugins_List_Table');
    $status = $fav_table->get_status();
    $page = 1;

    // var_dump($totals);
    $fav_table->prepare_items();
    $totals = $this->totals;

    ?>

    <ul class="subsubsub">
      <li class="all"><h3><?php _e('Favorite Plugins', 'wpfp'); ?></h3></li>
    </ul>
    <form method="post" id="bulk-action-form">

    <input type="hidden" name="plugin_status" value="<?php echo esc_attr($status) ?>" />
    <input type="hidden" name="paged" value="<?php echo esc_attr($page) ?>" />

    <?php $wp_list_table->display(); ?>
    <br><br>
    </form> <?php

  } // end display_favorite_table;

    
} // end WP_Favorite_Plugins;

endif;

new WP_Favorite_Plugins;