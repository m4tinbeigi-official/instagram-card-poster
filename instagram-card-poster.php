<?php
/*
Plugin Name: Instagram Card Poster Pro
Description: Advanced plugin to generate custom cards from WordPress posts, overlay featured images, add RTL text, and auto-post to Instagram. Supports customizable templates, logos, fonts, and more.
Version: 2.0
Author: Grok Assistant
License: GPL-2.0+
*/

// Security: Prevent direct access
if (!defined('ABSPATH')) exit;

// Include ArPHP for RTL support (download from https://github.com/khaled-alshamaa/ar-php)
if (file_exists(plugin_dir_path(__FILE__) . 'lib/Arabic.php')) {
    require_once plugin_dir_path(__FILE__) . 'lib/Arabic.php';
} else {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>ArPHP library missing! Download from GitHub and place in lib/ folder.</p></div>';
    });
}

// Check GD extension
if (!extension_loaded('gd')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>GD PHP extension is required for image processing!</p></div>';
    });
    return;
}

// Enqueue scripts for media uploader
add_action('admin_enqueue_scripts', 'icp_pro_enqueue_scripts');
function icp_pro_enqueue_scripts($hook) {
    if ($hook !== 'settings_page_icp_settings') return;
    wp_enqueue_media();
    wp_enqueue_script('icp-media-uploader', plugin_dir_url(__FILE__) . 'js/media-uploader.js', array('jquery'), '1.0', true);
    wp_enqueue_style('icp-admin-style', plugin_dir_url(__FILE__) . 'css/admin-style.css');
}

// JS for media uploader (create js/media-uploader.js file with this content)
/*
jQuery(document).ready(function($) {
    $('.icp-upload-button').click(function(e) {
        e.preventDefault();
        var button = $(this),
            custom_uploader = wp.media({
                title: 'Select Image',
                library: { type: 'image' },
                button: { text: 'Use this image' },
                multiple: false
            }).on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                button.prev('input').val(attachment.url);
                button.next('.icp-preview').html('<img src="' + attachment.url + '" style="max-width:200px;">');
            }).open();
    });
});
*/

// CSS for better UI (create css/admin-style.css)
/*
.icp-settings-wrap { max-width: 800px; }
.icp-section { margin-bottom: 20px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
.icp-preview { margin-top: 10px; }
*/

// Register settings page
add_action('admin_menu', 'icp_pro_add_admin_menu');
function icp_pro_add_admin_menu() {
    add_options_page('Instagram Card Poster Pro Settings', 'ICP Pro', 'manage_options', 'icp_settings', 'icp_pro_settings_page');
}

function icp_pro_settings_page() {
    ?>
    <div class="wrap icp-settings-wrap">
        <h1>Instagram Card Poster Pro Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('icp_pro_settings_group');
            do_settings_sections('icp_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'icp_pro_register_settings');
function icp_pro_register_settings() {
    register_setting('icp_pro_settings_group', 'icp_template_image_url');
    register_setting('icp_pro_settings_group', 'icp_logo_image_url');
    register_setting('icp_pro_settings_group', 'icp_logo_x');
    register_setting('icp_pro_settings_group', 'icp_logo_y');
    register_setting('icp_pro_settings_group', 'icp_font_path');
    register_setting('icp_pro_settings_group', 'icp_title_font_size');
    register_setting('icp_pro_settings_group', 'icp_title_color'); // e.g., #000000
    register_setting('icp_pro_settings_group', 'icp_title_x');
    register_setting('icp_pro_settings_group', 'icp_title_y');
    register_setting('icp_pro_settings_group', 'icp_caption_font_size');
    register_setting('icp_pro_settings_group', 'icp_caption_color');
    register_setting('icp_pro_settings_group', 'icp_caption_x');
    register_setting('icp_pro_settings_group', 'icp_caption_y');
    register_setting('icp_pro_settings_group', 'icp_overlay_resize_percent'); // e.g., 50 for 50%
    register_setting('icp_pro_settings_group', 'icp_caption_char_limit');
    register_setting('icp_pro_settings_group', 'icp_ig_access_token');
    register_setting('icp_pro_settings_group', 'icp_ig_user_id');
    register_setting('icp_pro_settings_group', 'icp_enable_auto_post'); // yes/no
    register_setting('icp_pro_settings_group', 'icp_image_format'); // jpeg/png

    add_settings_section('icp_general_section', 'General Settings', null, 'icp_settings');
    add_settings_section('icp_image_section', 'Image Customization', null, 'icp_settings');
    add_settings_section('icp_text_section', 'Text Customization', null, 'icp_settings');
    add_settings_section('icp_ig_section', 'Instagram Integration', null, 'icp_settings');

    // General
    add_settings_field('icp_template_image_url', 'Template Image URL', 'icp_template_image_callback', 'icp_settings', 'icp_general_section');
    add_settings_field('icp_logo_image_url', 'Logo Image URL', 'icp_logo_image_callback', 'icp_settings', 'icp_general_section');
    add_settings_field('icp_font_path', 'Font Path (TTF, e.g., Vazirmatn-Regular.ttf)', 'icp_font_path_callback', 'icp_settings', 'icp_general_section');
    add_settings_field('icp_enable_auto_post', 'Enable Auto-Post to Instagram', 'icp_enable_auto_post_callback', 'icp_settings', 'icp_general_section');
    add_settings_field('icp_image_format', 'Output Image Format', 'icp_image_format_callback', 'icp_settings', 'icp_general_section');

    // Image
    add_settings_field('icp_logo_x', 'Logo Position X', 'icp_logo_x_callback', 'icp_settings', 'icp_image_section');
    add_settings_field('icp_logo_y', 'Logo Position Y', 'icp_logo_y_callback', 'icp_settings', 'icp_image_section');
    add_settings_field('icp_overlay_resize_percent', 'Overlay Image Resize (%)', 'icp_overlay_resize_percent_callback', 'icp_settings', 'icp_image_section');

    // Text
    add_settings_field('icp_title_font_size', 'Title Font Size', 'icp_title_font_size_callback', 'icp_settings', 'icp_text_section');
    add_settings_field('icp_title_color', 'Title Color (hex)', 'icp_title_color_callback', 'icp_settings', 'icp_text_section');
    add_settings_field('icp_title_x', 'Title Position X', 'icp_title_x_callback', 'icp_settings', 'icp_text_section');
    add_settings_field('icp_title_y', 'Title Position Y', 'icp_title_y_callback', 'icp_settings', 'icp_text_section');
    add_settings_field('icp_caption_font_size', 'Caption Font Size', 'icp_caption_font_size_callback', 'icp_settings', 'icp_text_section');
    add_settings_field('icp_caption_color', 'Caption Color (hex)', 'icp_caption_color_callback', 'icp_settings', 'icp_text_section');
    add_settings_field('icp_caption_x', 'Caption Position X', 'icp_caption_x_callback', 'icp_settings', 'icp_text_section');
    add_settings_field('icp_caption_y', 'Caption Position Y', 'icp_caption_y_callback', 'icp_settings', 'icp_text_section');
    add_settings_field('icp_caption_char_limit', 'Caption Character Limit', 'icp_caption_char_limit_callback', 'icp_settings', 'icp_text_section');

    // Instagram
    add_settings_field('icp_ig_access_token', 'Instagram Access Token', 'icp_ig_access_token_callback', 'icp_settings', 'icp_ig_section');
    add_settings_field('icp_ig_user_id', 'Instagram User ID', 'icp_ig_user_id_callback', 'icp_settings', 'icp_ig_section');
}

// Callbacks with media uploader
function icp_template_image_callback() {
    $value = get_option('icp_template_image_url', '');
    echo '<input type="text" name="icp_template_image_url" id="icp_template_image_url" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<button class="button icp-upload-button">Upload Template</button>';
    echo '<div class="icp-preview"></div>';
}

function icp_logo_image_callback() {
    $value = get_option('icp_logo_image_url', '');
    echo '<input type="text" name="icp_logo_image_url" id="icp_logo_image_url" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<button class="button icp-upload-button">Upload Logo</button>';
    echo '<div class="icp-preview"></div>';
}

// Other callbacks (simple inputs)
function icp_font_path_callback() {
    $value = get_option('icp_font_path', plugin_dir_path(__FILE__) . 'fonts/Vazirmatn-Regular.ttf');
    echo '<input type="text" name="icp_font_path" value="' . esc_attr($value) . '" class="regular-text">';
}

function icp_logo_x_callback() {
    $value = get_option('icp_logo_x', 10);
    echo '<input type="number" name="icp_logo_x" value="' . esc_attr($value) . '">';
}

function icp_logo_y_callback() {
    $value = get_option('icp_logo_y', 10);
    echo '<input type="number" name="icp_logo_y" value="' . esc_attr($value) . '">';
}

function icp_title_font_size_callback() {
    $value = get_option('icp_title_font_size', 30);
    echo '<input type="number" name="icp_title_font_size" value="' . esc_attr($value) . '">';
}

function icp_title_color_callback() {
    $value = get_option('icp_title_color', '#000000');
    echo '<input type="text" name="icp_title_color" value="' . esc_attr($value) . '">';
}

function icp_title_x_callback() {
    $value = get_option('icp_title_x', 0); // 0 for center
    echo '<input type="number" name="icp_title_x" value="' . esc_attr($value) . '"> (0 for center)';
}

function icp_title_y_callback() {
    $value = get_option('icp_title_y', 50);
    echo '<input type="number" name="icp_title_y" value="' . esc_attr($value) . '">';
}

function icp_caption_font_size_callback() {
    $value = get_option('icp_caption_font_size', 20);
    echo '<input type="number" name="icp_caption_font_size" value="' . esc_attr($value) . '">';
}

function icp_caption_color_callback() {
    $value = get_option('icp_caption_color', '#000000');
    echo '<input type="text" name="icp_caption_color" value="' . esc_attr($value) . '">';
}

function icp_caption_x_callback() {
    $value = get_option('icp_caption_x', 0);
    echo '<input type="number" name="icp_caption_x" value="' . esc_attr($value) . '"> (0 for center)';
}

function icp_caption_y_callback() {
    $value = get_option('icp_caption_y', 0); // 0 for bottom
    echo '<input type="number" name="icp_caption_y" value="' . esc_attr($value) . '"> (0 for bottom)';
}

function icp_overlay_resize_percent_callback() {
    $value = get_option('icp_overlay_resize_percent', 50);
    echo '<input type="number" name="icp_overlay_resize_percent" value="' . esc_attr($value) . '" min="10" max="100">';
}

function icp_caption_char_limit_callback() {
    $value = get_option('icp_caption_char_limit', 200);
    echo '<input type="number" name="icp_caption_char_limit" value="' . esc_attr($value) . '">';
}

function icp_ig_access_token_callback() {
    $value = get_option('icp_ig_access_token', '');
    echo '<input type="text" name="icp_ig_access_token" value="' . esc_attr($value) . '" class="regular-text">';
}

function icp_ig_user_id_callback() {
    $value = get_option('icp_ig_user_id', '');
    echo '<input type="text" name="icp_ig_user_id" value="' . esc_attr($value) . '" class="regular-text">';
}

function icp_enable_auto_post_callback() {
    $value = get_option('icp_enable_auto_post', 'yes');
    echo '<select name="icp_enable_auto_post"><option value="yes" ' . selected($value, 'yes') . '>Yes</option><option value="no" ' . selected($value, 'no') . '>No</option></select>';
}

function icp_image_format_callback() {
    $value = get_option('icp_image_format', 'jpeg');
    echo '<select name="icp_image_format"><option value="jpeg" ' . selected($value, 'jpeg') . '>JPEG</option><option value="png" ' . selected($value, 'png') . '>PNG</option></select>';
}

// Hook to publish post
add_action('publish_post', 'icp_pro_generate_and_post', 10, 2);
function icp_pro_generate_and_post($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (get_option('icp_enable_auto_post', 'yes') !== 'yes') return;

    $featured_image_id = get_post_thumbnail_id($post_id);
    if (!$featured_image_id) {
        error_log('ICP Pro: No featured image for post ' . $post_id);
        return;
    }

    $featured_image_path = get_attached_file($featured_image_id);
    $title = get_the_title($post_id);
    $content = strip_tags(get_the_content(null, false, $post_id));
    $caption = mb_substr($content, 0, (int)get_option('icp_caption_char_limit', 200), 'UTF-8') . '...';

    // Get settings
    $template_url = get_option('icp_template_image_url');
    $template_path = str_replace(content_url(), WP_CONTENT_DIR, $template_url); // Convert URL to path
    $logo_url = get_option('icp_logo_image_url');
    $logo_path = $logo_url ? str_replace(content_url(), WP_CONTENT_DIR, $logo_url) : '';
    $font_path = get_option('icp_font_path');
    $output_path = icp_pro_generate_card($template_path, $featured_image_path, $title, $caption, $font_path, $logo_path);

    if ($output_path) {
        // Upload to media library
        $wp_upload_dir = wp_upload_dir();
        $attachment = array(
            'guid'           => $wp_upload_dir['url'] . '/' . basename($output_path),
            'post_mime_type' => 'image/' . get_option('icp_image_format', 'jpeg'),
            'post_title'     => 'ICP Card for ' . $title,
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $output_path, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $output_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Post to Instagram using wp_remote_post
        icp_pro_post_to_instagram($wp_upload_dir['url'] . '/' . basename($output_path), $caption);
    }
}

// Generate card with advanced options
function icp_pro_generate_card($template_path, $overlay_path, $title, $caption, $font_path, $logo_path = '') {
    // Detect template type
    $template_type = wp_check_filetype($template_path)['type'];
    if ($template_type === 'image/jpeg') {
        $template = imagecreatefromjpeg($template_path);
    } elseif ($template_type === 'image/png') {
        $template = imagecreatefrompng($template_path);
    } else {
        error_log('ICP Pro: Unsupported template format');
        return false;
    }

    $width = imagesx($template);
    $height = imagesy($template);

    // Overlay
    $overlay_type = wp_check_filetype($overlay_path)['type'];
    if ($overlay_type === 'image/jpeg') {
        $overlay = imagecreatefromjpeg($overlay_path);
    } elseif ($overlay_type === 'image/png') {
        $overlay = imagecreatefrompng($overlay_path);
    } else {
        imagedestroy($template);
        return false;
    }

    $overlay_width = imagesx($overlay);
    $overlay_height = imagesy($overlay);
    $resize_percent = (int)get_option('icp_overlay_resize_percent', 50) / 100;
    $new_overlay_width = $width * $resize_percent;
    $new_overlay_height = ($overlay_height / $overlay_width) * $new_overlay_width;
    $resized_overlay = imagecreatetruecolor($new_overlay_width, $new_overlay_height);
    imagealphablending($resized_overlay, false);
    imagesavealpha($resized_overlay, true);
    imagecopyresampled($resized_overlay, $overlay, 0, 0, 0, 0, $new_overlay_width, $new_overlay_height, $overlay_width, $overlay_height);

    $center_x = ($width - $new_overlay_width) / 2;
    $center_y = ($height - $new_overlay_height) / 2;
    imagecopy($template, $resized_overlay, $center_x, $center_y, 0, 0, $new_overlay_width, $new_overlay_height);

    // Logo
    if ($logo_path) {
        $logo_type = wp_check_filetype($logo_path)['type'];
        $logo = ($logo_type === 'image/png') ? imagecreatefrompng($logo_path) : imagecreatefromjpeg($logo_path);
        $logo_width = imagesx($logo);
        $logo_height = imagesy($logo);
        $logo_x = (int)get_option('icp_logo_x', 10);
        $logo_y = (int)get_option('icp_logo_y', 10);
        imagecopy($template, $logo, $logo_x, $logo_y, 0, 0, $logo_width, $logo_height);
        imagedestroy($logo);
    }

    // RTL text
    if (class_exists('I18N_Arabic')) {
        $Arabic = new I18N_Arabic('Glyphs');
        $title_rtl = $Arabic->utf8Glyphs($title);
        $caption_rtl = $Arabic->utf8Glyphs($caption);
    } else {
        $title_rtl = $title;
        $caption_rtl = $caption;
    }

    // Parse colors
    function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    $title_color_hex = get_option('icp_title_color', '#000000');
    $title_color_rgb = hex_to_rgb($title_color_hex);
    $title_color = imagecolorallocate($template, $title_color_rgb[0], $title_color_rgb[1], $title_color_rgb[2]);

    $caption_color_hex = get_option('icp_caption_color', '#000000');
    $caption_color_rgb = hex_to_rgb($caption_color_hex);
    $caption_color = imagecolorallocate($template, $caption_color_rgb[0], $caption_color_rgb[1], $caption_color_rgb[2]);

    // Title text
    $title_size = (int)get_option('icp_title_font_size', 30);
    $title_bbox = imagettfbbox($title_size, 0, $font_path, $title_rtl);
    $title_text_width = $title_bbox[4] - $title_bbox[0];
    $title_x_opt = (int)get_option('icp_title_x', 0);
    $title_x = ($title_x_opt === 0) ? ($width - $title_text_width) / 2 : $title_x_opt;
    $title_y = (int)get_option('icp_title_y', 50);
    imagettftext($template, $title_size, 0, $title_x, $title_y, $title_color, $font_path, $title_rtl);

    // Caption text (with simple wrap if too long)
    $caption_size = (int)get_option('icp_caption_font_size', 20);
    $caption_bbox = imagettfbbox($caption_size, 0, $font_path, $caption_rtl);
    $caption_text_width = $caption_bbox[4] - $caption_bbox[0];
    $caption_x_opt = (int)get_option('icp_caption_x', 0);
    $caption_x = ($caption_x_opt === 0) ? ($width - $caption_text_width) / 2 : $caption_x_opt;
    $caption_y_opt = (int)get_option('icp_caption_y', 0);
    $caption_y = ($caption_y_opt === 0) ? $height - 100 : $caption_y_opt;
    imagettftext($template, $caption_size, 0, $caption_x, $caption_y, $caption_color, $font_path, $caption_rtl);

    // Save with format
    $format = get_option('icp_image_format', 'jpeg');
    $output_path = wp_upload_dir()['path'] . '/icp_card_' . time() . '.' . $format;
    if ($format === 'jpeg') {
        imagejpeg($template, $output_path, 90);
    } else {
        imagepng($template, $output_path, 9);
    }

    // Cleanup
    imagedestroy($template);
    imagedestroy($overlay);
    imagedestroy($resized_overlay);

    return $output_path;
}

// Post to Instagram using wp_remote_post
function icp_pro_post_to_instagram($image_url, $caption) {
    $access_token = get_option('icp_ig_access_token');
    $ig_user_id = get_option('icp_ig_user_id');
    if (empty($access_token) || empty($ig_user_id)) {
        error_log('ICP Pro: Instagram credentials missing');
        return;
    }

    // Step 1: Create media container
    $url = "https://graph.instagram.com/v23.0/{$ig_user_id}/media";
    $args = array(
        'body' => array(
            'image_url' => $image_url,
            'caption' => $caption,
            'access_token' => $access_token
        ),
        'timeout' => 30
    );
    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        error_log('ICP Pro: Instagram API error - ' . $response->get_error_message());
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['id'])) {
        $container_id = $body['id'];

        // Step 2: Publish
        $publish_url = "https://graph.instagram.com/v23.0/{$ig_user_id}/media_publish";
        $publish_args = array(
            'body' => array(
                'creation_id' => $container_id,
                'access_token' => $access_token
            ),
            'timeout' => 30
        );
        $publish_response = wp_remote_post($publish_url, $publish_args);

        if (is_wp_error($publish_response)) {
            error_log('ICP Pro: Instagram publish error - ' . $publish_response->get_error_message());
        } else {
            error_log('ICP Pro: Instagram post successful - ' . wp_remote_retrieve_body($publish_response));
        }
    } else {
        error_log('ICP Pro: Failed to create Instagram container - ' . print_r($body, true));
    }
}

// Shortcode for manual generation: [icp_pro_card post_id="123"]
add_shortcode('icp_pro_card', 'icp_pro_shortcode');
function icp_pro_shortcode($atts) {
    $post_id = $atts['post_id'] ?? get_the_ID();
    icp_pro_generate_and_post($post_id, get_post($post_id));
    return 'Pro Card generated and posted!';
}