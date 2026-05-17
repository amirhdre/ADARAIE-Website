<?php

/**
 * Moments timeline data injector.
 * Reads Moment posts and feeds them to the front-end JS as window.MOMENTS_DATA.
 *
 * Defensively coded — bails silently on any error rather than crashing the page.
 */
add_action('wp_footer', function() {

    // --- SAFETY GUARDS ----------------------------------------------------
    // Bail if core functions don't exist (shouldn't happen, but defensive)
    if (!function_exists('is_page') || !function_exists('get_post')) {
        return;
    }

    // Only run on the Moments page
    if (!is_page('moments')) {
        return;
    }

    // Bail if WP_Query isn't available (impossible, but defensive)
    if (!class_exists('WP_Query')) {
        return;
    }

    // --- QUERY MOMENTS ----------------------------------------------------
    $query = new WP_Query(array(
        'post_type'      => 'moment',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish',
    ));

    $timeline = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            if (!$post_id) continue;

            $title    = get_the_title();
            $subtitle = has_excerpt() ? get_the_excerpt() : '';

            // Compute year/month from post date
            $year  = (int) get_the_date('Y');
            $month = (int) get_the_date('n'); // 1-12

            // Compute season label from month
            $season = '';
            if ($month >= 3 && $month <= 5)       $season = 'Spring';
            elseif ($month >= 6 && $month <= 8)   $season = 'Summer';
            elseif ($month >= 9 && $month <= 11)  $season = 'Fall';
            elseif ($month === 12 || $month <= 2) $season = 'Winter';

            // Display period: "Summer 2025"
            $display_period = $season ? $season . ' ' . $year : (string) $year;

            // --- CATEGORIES (safe) -----------------------------------------
            $cat_terms = array();
            if (function_exists('wp_get_post_terms') && taxonomy_exists('moment_category')) {
                $terms = wp_get_post_terms($post_id, 'moment_category', array('fields' => 'slugs'));
                if (!is_wp_error($terms) && is_array($terms)) {
                    $cat_terms = $terms;
                }
            }

            // --- CITY (safe, optional) -------------------------------------
            $city = '';
            if (function_exists('wp_get_post_terms') && taxonomy_exists('moment_city')) {
                $city_terms = wp_get_post_terms($post_id, 'moment_city', array('fields' => 'names'));
                if (!is_wp_error($city_terms) && !empty($city_terms) && is_array($city_terms)) {
                    $city = $city_terms[0];
                }
            }

            // --- EXTRACT IMAGES FROM POST CONTENT --------------------------
            $photo_arr = array();
            $content = get_the_content();

            if ($content && function_exists('parse_blocks') && function_exists('wp_get_attachment_image_url')) {
                $blocks = parse_blocks($content);
                if (is_array($blocks)) {
                    foreach ($blocks as $block) {
                        if (!is_array($block) || !isset($block['blockName'])) continue;

                        // Gallery block
                        if ($block['blockName'] === 'core/gallery') {
                            // Modern format: innerBlocks contain image blocks
                            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                                foreach ($block['innerBlocks'] as $img_block) {
                                    if (!is_array($img_block)) continue;
                                    if (($img_block['blockName'] ?? '') !== 'core/image') continue;
                                    $img_id = $img_block['attrs']['id'] ?? null;
                                    if (!$img_id) continue;

                                    $img_url = wp_get_attachment_image_url($img_id, 'large');
                                    $att = get_post($img_id);
                                    $caption = ($att && isset($att->post_excerpt)) ? $att->post_excerpt : '';

                                    if ($img_url) {
                                        $photo_arr[] = array(
                                            'src'     => esc_url($img_url),
                                            'caption' => esc_html($caption ?: ''),
                                        );
                                    }
                                }
                            }
                            // Legacy format: ids attribute
                            elseif (!empty($block['attrs']['ids']) && is_array($block['attrs']['ids'])) {
                                foreach ($block['attrs']['ids'] as $img_id) {
                                    if (!$img_id) continue;
                                    $img_url = wp_get_attachment_image_url($img_id, 'large');
                                    $att = get_post($img_id);
                                    $caption = ($att && isset($att->post_excerpt)) ? $att->post_excerpt : '';
                                    if ($img_url) {
                                        $photo_arr[] = array(
                                            'src'     => esc_url($img_url),
                                            'caption' => esc_html($caption ?: ''),
                                        );
                                    }
                                }
                            }
                        }

                        // Standalone image block (in case user adds individual images)
                        elseif ($block['blockName'] === 'core/image') {
                            $img_id = $block['attrs']['id'] ?? null;
                            if (!$img_id) continue;
                            $img_url = wp_get_attachment_image_url($img_id, 'large');
                            $att = get_post($img_id);
                            $caption = ($att && isset($att->post_excerpt)) ? $att->post_excerpt : '';
                            if ($img_url) {
                                $photo_arr[] = array(
                                    'src'     => esc_url($img_url),
                                    'caption' => esc_html($caption ?: ''),
                                );
                            }
                        }
                    }
                }
            }

            // Skip moments with no photos
            if (empty($photo_arr)) continue;

            $timeline[] = array(
                'year'       => $year,
                'month'      => $month,
                'period'     => $display_period,
                'title'      => $title,
                'subtitle'   => $subtitle,
                'categories' => $cat_terms,
                'city'       => $city,
                'photos'     => $photo_arr,
            );
        }
        wp_reset_postdata();
    }

    // --- BUILD CATEGORY LIST FOR FILTER UI --------------------------------
    $all_cats = array();
    if (function_exists('get_terms') && taxonomy_exists('moment_category')) {
        $terms = get_terms(array(
            'taxonomy'   => 'moment_category',
            'hide_empty' => true,
        ));
        if (!is_wp_error($terms) && is_array($terms)) {
            foreach ($terms as $term) {
                if (isset($term->slug) && isset($term->name)) {
                    $all_cats[] = array(
                        'slug' => $term->slug,
                        'name' => $term->name,
                    );
                }
            }
        }
    }

    // --- OUTPUT JS DATA ---------------------------------------------------
    if (function_exists('wp_json_encode')) {
        echo '<script>';
        echo 'window.MOMENTS_DATA = ' . wp_json_encode($timeline) . ';';
        echo 'window.MOMENTS_CATEGORIES = ' . wp_json_encode($all_cats) . ';';
        echo '</script>';
    }
});