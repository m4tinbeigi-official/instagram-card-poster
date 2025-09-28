<?php
/*
Plugin Name: Instagram Card Poster
Description: Generates custom card from post, overlays featured image, adds text, and posts to Instagram.
Version: 1.0
Author: Grok Assistant
*/

// Include ArPHP for RTL support
require_once plugin_dir_path(__FILE__) . 'lib/Arabic.php'; // Adjust path to Arabic.php

// Register settings page
add_action('admin_menu', 'icp_add_admin_menu');
function icp_add_admin_menu() {
    add_options_page('Instagram Card Poster Settings', 'Instagram Card Poster', 'manage_options', 'icp_settings', 'icp_settings_page');
}

function icp_settings_page() {
    ?>
    <div class="wrap">
        <h1>Instagram Card Poster Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('icp_settings_group');
            do_settings_sections('icp_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'icp_register_settings');
function icp_register_settings() {
    register_setting('icp_settings_group', 'icp_template_image'); // URL or ID of template image
    register_setting('icp_settings_group', 'icp_logo_image'); // URL or ID of logo
    register_setting('icp_settings_group', 'icp_logo_x'); // Logo position X
    register_setting('icp_settings_group', 'icp_logo_y'); // Logo position Y
    register_setting('icp_settings_group', 'icp_font_path'); // Path to TTF font
    register_setting('icp_settings_group', 'icp_ig_access_token');
    register_setting('icp_settings_group', 'icp_ig_user_id');

    add_settings_section('icp_main_section', 'Main Settings', null, 'icp_settings');

    add_settings_field('icp_template_image', 'Template Image ID/URL', 'icp_template_image_callback', 'icp_settings', 'icp_main_section');
    add_settings_field('icp_logo_image', 'Logo Image ID/URL', 'icp_logo_image_callback', 'icp_settings', 'icp_main_section');
    add_settings_field('icp_logo_x', 'Logo Position X', 'icp_logo_x_callback', 'icp_settings', 'icp_main_section');
    add_settings_field('icp_logo_y', 'Logo Position Y', 'icp_logo_y_callback', 'icp_settings', 'icp_main_section');
    add_settings_field('icp_font_path', 'Font Path (TTF)', 'icp_font_path_callback', 'icp_settings', 'icp_main_section');
    add_settings_field('icp_ig_access_token', 'Instagram Access Token', 'icp_ig_access_token_callback', 'icp_settings', 'icp_main_section');
    add_settings_field('icp_ig_user_id', 'Instagram User ID', 'icp_ig_user_id_callback', 'icp_settings', 'icp_main_section');
}

// Callback functions for fields (simple text inputs for now)
function icp_template_image_callback() {
    $value = get_option('icp_template_image', '');
    echo '<input type="text" name="icp_template_image" value="' . esc_attr($value) . '" /> (Upload to media and enter URL or ID)';
}
// Similar for others...
function icp_logo_image_callback() {
    $value = get_option('icp_logo_image', '');
    echo '<input type="text" name="icp_logo_image" value="' . esc_attr($value) . '" />';
}
function icp_logo_x_callback() {
    $value = get_option('icp_logo_x', '10');
    echo '<input type="number" name="icp_logo_x" value="' . esc_attr($value) . '" />';
}
function icp_logo_y_callback() {
    $value = get_option('icp_logo_y', '10');
    echo '<input type="number" name="icp_logo_y" value="' . esc_attr($value) . '" />';
}
function icp_font_path_callback() {
    $value = get_option('icp_font_path', plugin_dir_path(__FILE__) . 'fonts/Vazirmatn-Regular.ttf');
    echo '<input type="text" name="icp_font_path" value="' . esc_attr($value) . '" />';
}
function icp_ig_access_token_callback() {
    $value = get_option('icp_ig_access_token', '');
    echo '<input type="text" name="icp_ig_access_token" value="' . esc_attr($value) . '" />';
}
function icp_ig_user_id_callback() {
    $value = get_option('icp_ig_user_id', '');
    echo '<input type="text" name="icp_ig_user_id" value="' . esc_attr($value) . '" />';
}

// Hook to publish post
add_action('publish_post', 'icp_generate_and_post_to_instagram', 10, 2);
function icp_generate_and_post_to_instagram($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Get featured image
    $featured_image_id = get_post_thumbnail_id($post_id);
    if (!$featured_image_id) return; // No image, skip

    $featured_image_path = get_attached_file($featured_image_id);

    // Get title and caption
    $title = get_the_title($post_id);
    $content = strip_tags(get_the_content(null, false, $post_id));
    $caption = wp_trim_words($content, 100, '...'); // Limited to ~200 chars
    if (empty($caption)) $caption = get_the_excerpt($post_id);

    // Get settings
    $template_image = get_option('icp_template_image'); // Assume URL
    $logo_image = get_option('icp_logo_image');
    $logo_x = (int)get_option('icp_logo_x', 10);
    $logo_y = (int)get_option('icp_logo_y', 10);
    $font_path = get_option('icp_font_path');

    // Generate card image
    $output_image_path = icp_generate_card($template_image, $featured_image_path, $title, $caption, $font_path, $logo_image, $logo_x, $logo_y);

    if ($output_image_path) {
        // Upload to media library
        $attachment = array(
            'guid'           => wp_upload_dir()['url'] . '/' . basename($output_image_path),
            'post_mime_type' => 'image/jpeg',
            'post_title'     => 'Instagram Card for ' . $title,
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $output_image_path, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $output_image_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Post to Instagram
        icp_post_to_instagram($output_image_path, $caption);
    }
}

// Function to generate card using GD
function icp_generate_card($template_path, $overlay_path, $title, $caption, $font_path, $logo_path = '', $logo_x = 10, $logo_y = 10) {
    // Load template (assume JPEG)
    $template = imagecreatefromjpeg($template_path); // Or use imagecreatefrompng if PNG
    $width = imagesx($template);
    $height = imagesy($template);

    // Load overlay image and resize to 50% of template width, center it
    $overlay = imagecreatefromjpeg($overlay_path); // Assume JPEG
    $overlay_width = imagesx($overlay);
    $overlay_height = imagesy($overlay);
    $new_overlay_width = $width * 0.5;
    $new_overlay_height = ($overlay_height / $overlay_width) * $new_overlay_width;
    $resized_overlay = imagecreatetruecolor($new_overlay_width, $new_overlay_height);
    imagecopyresampled($resized_overlay, $overlay, 0, 0, 0, 0, $new_overlay_width, $new_overlay_height, $overlay_width, $overlay_height);

    // Center position
    $center_x = ($width - $new_overlay_width) / 2;
    $center_y = ($height - $new_overlay_height) / 2;
    imagecopy($template, $resized_overlay, $center_x, $center_y, 0, 0, $new_overlay_width, $new_overlay_height);

    // Add logo if provided
    if ($logo_path) {
        $logo = imagecreatefrompng($logo_path); // Assume PNG for transparency
        $logo_width = imagesx($logo);
        $logo_height = imagesy($logo);
        imagecopy($template, $logo, $logo_x, $logo_y, 0, 0, $logo_width, $logo_height);
        imagedestroy($logo);
    }

    // RTL process with ArPHP
    $Arabic = new I18N_Arabic('Glyphs');
    $title_rtl = $Arabic->utf8Glyphs($title);
    $caption_rtl = $Arabic->utf8Glyphs($caption);

    // Add title text (e.g., top center, size 30, black)
    $text_color = imagecolorallocate($template, 0, 0, 0);
    $title_y = 50; // Adjust position
    $title_bbox = imagettfbbox(30, 0, $font_path, $title_rtl);
    $title_x = ($width - ($title_bbox[4] - $title_bbox[0])) / 2;
    imagettftext($template, 30, 0, $title_x, $title_y, $text_color, $font_path, $title_rtl);

    // Add caption text (bottom, size 20, wrapped if needed)
    $caption_y = $height - 100; // Adjust
    $caption_bbox = imagettfbbox(20, 0, $font_path, $caption_rtl);
    $caption_x = ($width - ($caption_bbox[4] - $caption_bbox[0])) / 2;
    imagettftext($template, 20, 0, $caption_x, $caption_y, $text_color, $font_path, $caption_rtl);

    // Save output
    $output_path = wp_upload_dir()['path'] . '/instagram_card_' . time() . '.jpg';
    imagejpeg($template, $output_path, 90);

    // Clean up
    imagedestroy($template);
    imagedestroy($overlay);
    imagedestroy($resized_overlay);

    return $output_path;
}

// Function to post to Instagram using Graph API
function icp_post_to_instagram($image_path, $caption) {
    $access_token = get_option('icp_ig_access_token');
    $ig_user_id = get_option('icp_ig_user_id');
    if (empty($access_token) || empty($ig_user_id)) return; // Skip if not set

    // Image must be public URL - upload to temp public URL or use media URL
    $image_url = wp_get_attachment_url(wp_insert_attachment(array('guid' => $image_path))); // Temp, better to make public

    // Step 1: Create container
    $url = "https://graph.instagram.com/v23.0/{$ig_user_id}/media";
    $data = array(
        'image_url' => $image_url,
        'caption' => urlencode($caption),
        'access_token' => $access_token
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $response_data = json_decode($response, true);
    if (isset($response_data['id'])) {
        $container_id = $response_data['id'];

        // Step 2: Publish
        $publish_url = "https://graph.instagram.com/v23.0/{$ig_user_id}/media_publish";
        $publish_data = array(
            'creation_id' => $container_id,
            'access_token' => $access_token
        );

        $ch = curl_init($publish_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($publish_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $publish_response = curl_exec($ch);
        curl_close($ch);

        // Log response if needed
        error_log('Instagram Post Response: ' . $publish_response);
    }
}

// Shortcode for manual test: [instagram_card post_id="123"]
add_shortcode('instagram_card', 'icp_shortcode');
function icp_shortcode($atts) {
    $post_id = $atts['post_id'] ?? get_the_ID();
    icp_generate_and_post_to_instagram($post_id, get_post($post_id));
    return 'Card generated and posted!';
}