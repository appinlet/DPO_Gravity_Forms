<?php

/**
 * Plugin Name: Gravity Forms DPO Pay Add-On
 * Plugin URI: https://github.com/DPO-Group/DPO_Gravity_Forms
 * Description: Integrates Gravity Forms with DPO Pay, a An African payment gateway.
 * Version: 1.0.5
 * Minimum Gravity Forms Version: 2.2.5
 * Tested Gravity Forms Version: 2.6.9
 * Author: DPO Group
 * Author URI: https://www.dpogroup.com/africa/
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 * Text Domain: gravity-forms-dpo-group
 * Domain Path: /languages
 *
 * Copyright: © 2023 DPO Group
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

add_action('plugins_loaded', 'dpo_group_init');

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function dpo_group_init()
{
    /**
     * Auto updates from GIT
     *
     * @since 1.0.0
     *
     */

    require_once 'includes/updater.class.php';

    if (is_admin()) {
        // note the use of is_admin() to double check that this is happening in the admin

        $config = array(
            'slug'               => plugin_basename(__FILE__),
            'proper_folder_name' => 'gravity-forms-dpo-group-plugin',
            'api_url'            => 'https://api.github.com/repos/DPO-Group/DPO_Gravity_Forms',
            'raw_url'            => 'https://raw.github.com/DPO-Group/DPO_Gravity_Forms/master',
            'github_url'         => 'https://github.com/DPO-Group/DPO_Gravity_Forms',
            'zip_url'            => 'https://github.com/DPO-Group/DPO_Gravity_Forms/archive/master.zip',
            'homepage'           => 'https://github.com/DPO-Group/DPO_Gravity_Forms',
            'sslverify'          => true,
            'requires'           => '4.0',
            'tested'             => '6.0.2',
            'readme'             => 'README.md',
            'access_token'       => '',
        );

        new WP_GitHub_Updater_DPO_Group($config);
    }
}

ob_start();
if ( !headers_sent() && empty(session_id())) {
    try {
        session_start();
    } catch (Exception $e) {
        // Catch exception
    }
}

add_action('gform_loaded', array('GF_DPO_Group_Bootstrap', 'load'), 5);

/**
 * Class GF_DPO_Group_Bootstrap
 * Loads the Gravity Forms DPO Pay Add-on
 */
class GF_DPO_Group_Bootstrap
{

    public static function load()
    {
        if ( ! method_exists('GFForms', 'include_payment_addon_framework')) {
            return;
        }

        require_once 'class-gf-dpo-group.php';

        GFAddOn::register('GF_DPO_Group');
    }

}

// Filters for payment status message
function dpo_group_change_message($message, $form)
{
    if (isset($_SESSION['trans_failed']) && ! empty($_SESSION['trans_failed']) && strlen(
                                                                                      $_SESSION['trans_failed']
                                                                                  ) > 0) {
        $err_msg = $_SESSION['trans_failed'];

        return "<div class='validation_error'>" . $_SESSION['trans_failed'] . '</div>';
    } elseif (isset($_SESSION['trans_declined']) && ! empty($_SESSION['trans_declined'])) {
        $err_msg = $_SESSION['trans_declined'];

        return "<div class='validation_error'>" . $_SESSION['trans_declined'] . '</div>';
    } else {
        return $message;
    }
}

add_filter('gform_pre_render', 'dpo_group_gform_pre_render_callback');

function dpo_group_gform_pre_render_callback($form)
{
    ob_start();
    ob_clean();
    $form_id = $form['id'];
    define("SCRIPT_DPO", '<script type="text/javascript">');
    define("QUERY_DPO", 'jQuery(document).ready(function($){');
    define("QUERY_FORM_DPO", 'jQuery("#gform_');
    define('APPEND_DPO', ' .gform_heading").append("<div class=\"validation_error\">');
    define('DIV_TAG_DPO_CLOSING', '</div>")');
    define('SCRIPT_TAG_DPO_CLOSING', '</script>');

    if (isset($_SESSION['trans_failed']) && ! empty($_SESSION['trans_failed'])) {
        $msg = $_SESSION['trans_failed'];
        echo constant('SCRIPT_DPO');
        echo constant('QUERY_DPO');
        echo constant('QUERY_FORM_DPO') . $form_id . constant('APPEND_DPO') . $msg . constant('DIV_TAG_DPO_CLOSING');
        echo '});';
        echo constant('SCRIPT_TAG_DPO_CLOSING');
    } elseif (isset($_SESSION['trans_declined']) && ! empty($_SESSION['trans_declined'])) {
        $msg = $_SESSION['trans_declined'];
        echo constant('SCRIPT_DPO');
        echo constant('QUERY_DPO');
        echo constant('QUERY_FORM_DPO') . $form_id . constant('APPEND_DPO') . $msg . constant('DIV_TAG_DPO_CLOSING');
        echo '});';
        echo constant('SCRIPT_TAG_DPO_CLOSING');
    } elseif (isset($_SESSION['trans_cancelled']) && ! empty($_SESSION['trans_cancelled'])) {
        $msg = $_SESSION['trans_cancelled'];
        echo constant('SCRIPT_DPO');
        echo constant('QUERY_DPO');
        echo constant('QUERY_FORM_DPO') . $form_id . constant('APPEND_DPO') . $msg . constant('DIV_TAG_DPO_CLOSING');
        echo '});';
        echo constant('SCRIPT_TAG_DPO_CLOSING');
    }

    return $form;
}

add_filter('gform_pre_validation', 'dpo_group_cleanTransaction_status');

function dpo_group_cleanTransaction_status($form)
{
    unset($_SESSION['trans_failed']);
    unset($_SESSION['trans_declined']);
    unset($_SESSION['trans_cancelled']);

    return $form;
}

add_filter('gform_after_submission', 'dpo_group_gw_conditional_requirement');

function dpo_group_gw_conditional_requirement($form)
{
    if (isset($_SESSION['trans_failed']) && ! empty($_SESSION['trans_failed'])) {
        $confirmation = $_SESSION['trans_failed'];
        add_filter('gform_validation_message', 'dpo_group_change_message', 10, 2);
    } elseif (isset($_SESSION['trans_declined']) && ! empty($_SESSION['trans_declined'])) {
        $confirmation = $_SESSION['trans_declined'];
        add_filter('gform_validation_message', 'dpo_group_change_message', 10, 2);
    }

    return $form;
}

/**
 * Encrypt and decrypt
 *
 * @param string $string string to be encrypted/decrypted
 * @param string $action what to do with this? e for encrypt, d for decrypt
 */
function DPO_Group_GF_encryption($string, $action = 'e'): bool|string
{
    // you may change these values to your own
    /** @noinspection PhpUndefinedConstantInspection */
    $secret_key = AUTH_SALT;
    /** @noinspection PhpUndefinedConstantInspection */
    $secret_iv = NONCE_SALT;

    $output         = false;
    $encrypt_method = "AES-256-CBC";
    $key            = hash('sha256', $secret_key);
    $iv             = substr(hash('sha256', $secret_iv), 0, 16);

    if ($action == 'e') {
        $output = rtrim(base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv)), '=');
    } elseif ($action == 'd') {
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
    }

    return $output;
}
